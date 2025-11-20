<?php

namespace App\Traits;

use App\Models\PostAllowedValue;
use App\Models\UserProfile;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait UserProfileHelper {

    /**
     * Setup the UserProfile query
     * This method is used to setup the query for the UserProfile model
     * It applies sorting, filtering, selecting, and pagination
     * It also loads the relations for the UserProfile model
     * 
     * @param Request $request The request object
     * @param mixed $query The query object
     * @param string $methods The method to call for the query
     * @return mixed The modified query object
     * 
     * @example | $query = $this->setupUserProfileQuery($request, $query, 'buildQuery');
     * 
     */
    protected function setupUserProfileQuery(Request $request, $query, $methods): mixed {
        $relationKeyFields = $this->getRelationKeyFields($request, ['user' => 'user_id']);

        $this->modifyRequestSelect($request, [...['id'], ...$relationKeyFields]);

        $query = $this->loadUserRelation($request, $query, 'user_id');

        $query = $this->loadFavoriteTechsRelation($request, $query);

        $query = $this->$methods($request, $query, 'user_profile');
        if ($query instanceof JsonResponse) {
            return $query;
        }

        return $query;
    }


    /**
     * Apply access filters based on user role
     * Admins and moderators can see all profiles
     * Regular users can only see public profiles and their own
     * 
     * @param Request $request The request containing the user
     * @return \Illuminate\Database\Eloquent\Builder Query with appropriate filters
     * 
     * @example | $query = $this->applyProfileAccessFilters($request);
     */
    private function applyProfileAccessFilters(Request $request) {
        $user = $request->user();

        if ($user->role === 'admin' || $user->role === 'moderator') {
            return UserProfile::query();
        } else {
            return UserProfile::query()->where(function ($subQuery) use ($user) {
                $subQuery->where('is_public', true)->orWhere('user_id', $user->id);
            });
        }
    }


    /**
     * Load the favorite_techs relation
     * 
     * @param Request $request
     * @param mixed $query Builder|LengthAwarePaginator|Collection
     * @return mixed Builder|LengthAwarePaginator|Collection
     * 
     * @example | $this->loadFavoriteTechsRelation($request, $query)
     */
    private function loadFavoriteTechsRelation(Request $request, $query): mixed {

        /**
         * If the request does not have the 'select' parameter or if 'favorite_techs' is selected,
         * we will load the favorite_techs relation by default.
         */
        if (!$request->has('select') || $this->isSelected($request, 'favorite_techs')) {
            $this->removeFromSelect($request, ['favorite_techs']);

            /**
             * Load the favorite_techs relation by Default
             * 
             * Explicit table.column AS alias format is used for many-to-many relationships
             * This is to avoid ambiguity in the result set, especially when joining multiple tables.
             */
            $tableName = $query->getModel()->favoriteTechs()->getRelated()->getTable();

            $defaultColumns = [
                "$tableName.id as id",
                "$tableName.name as name"
            ];

            $selectedFields = $this->getSelectRelationFields($request, $tableName, $defaultColumns, 'favorite_techs');

            $query = $this->loadRelations($request, $query, [
                ['relation' => 'favoriteTechs', 'foreignKey' => 'id', 'columns' => $selectedFields],
            ]);

            return $query;
        }

        return $query;
    }


    /**
     * Sync favorite languages for the user profile
     * 
     * @param UserProfile $userProfile The user profile to update
     * @param array $favoriteTechs Array of favorite technology names
     * @return null|JsonResponse Returns null if no changes are needed, or a JsonResponse with an error if validation fails
     */
    // TODO: Rename this method to syncTechStack to better reflect its purpose and change Relation name accordingly
    protected function syncFavoriteTechs(UserProfile $userProfile, array $favoriteTechs): null|JsonResponse {
        $current = $userProfile->favoriteTechs()->pluck('post_allowed_value_id', 'name')->toArray();
        $names = array_keys($current);
        sort($names);
        sort($favoriteTechs);

        if ($names !== $favoriteTechs) {
            $allowedIds = PostAllowedValue::whereIn('type', ['language', 'technology'])->whereIn('name', $favoriteTechs)->pluck('id')->toArray();
            sort($allowedIds);
            sort($current);

            $removeTechs = array_diff($current, $allowedIds);
            $addTechs = array_diff($allowedIds, $current);

            if (count($allowedIds) !== count($favoriteTechs)) {
                foreach ($favoriteTechs as $techName) {
                    if (!in_array($techName, $names)) {
                        return $this->errorResponse("Technology '$techName' is not allowed", 'FORBIDDEN_TECHNOLOGY', 422);
                    }
                }
            }

            $pivotTable = $userProfile->favoriteTechs()->getTable();
            DB::table($pivotTable)->where('user_profile_id', $userProfile->id)->whereIn('post_allowed_value_id', $removeTechs)->delete();


            if (!empty($addTechs)) {
                $insertData = [];
                foreach ($addTechs as $techId) {
                    $insertData[] = [
                        'user_profile_id' => $userProfile->id,
                        'post_allowed_value_id' => $techId,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }

                DB::table($pivotTable)->insert($insertData);
            }
        }
        return null;
    }
}
