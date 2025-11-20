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

        $query = $this->loadFavoriteLanguagesRelation($request, $query);

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
     * Load the favorite_languages relation
     * 
     * @param Request $request
     * @param mixed $query Builder|LengthAwarePaginator|Collection
     * @return mixed Builder|LengthAwarePaginator|Collection
     * 
     * @example | $this->loadFavoriteLanguagesRelation($request, $query)
     */
    private function loadFavoriteLanguagesRelation(Request $request, $query): mixed {

        /**
         * If the request does not have the 'select' parameter or if 'favorite_languages' is selected,
         * we will load the favorite_languages relation by default.
         */
        if (!$request->has('select') || $this->isSelected($request, 'favorite_languages')) {
            $this->removeFromSelect($request, ['favorite_languages']);

            /**
             * Load the favorite_languages relation by Default
             * 
             * Explicit table.column AS alias format is used for many-to-many relationships
             * This is to avoid ambiguity in the result set, especially when joining multiple tables.
             */
            $tableName = $query->getModel()->favoriteLanguages()->getRelated()->getTable();

            $defaultColumns = [
                "$tableName.id as id",
                "$tableName.name as name"
            ];

            $selectedFields = $this->getSelectRelationFields($request, $tableName, $defaultColumns, 'favorite_languages');

            $query = $this->loadRelations($request, $query, [
                ['relation' => 'favoriteLanguages', 'foreignKey' => 'id', 'columns' => $selectedFields],
            ]);

            return $query;
        }

        return $query;
    }


    /**
     * Sync favorite languages for the user profile
     * 
     * @param UserProfile $userProfile The user profile to update
     * @param array $favoriteLanguages Array of favorite language names
     * @return null|JsonResponse Returns null if no changes are needed, or a JsonResponse with an error if validation fails
     */
    // TODO: Rename this method to syncTechStack to better reflect its purpose and change Relation name accordingly
    protected function syncFavoriteLanguages(UserProfile $userProfile, array $favoriteLanguages): null|JsonResponse {
        $current = $userProfile->favoriteLanguages()->pluck('post_allowed_value_id', 'name')->toArray();
        $names = array_keys($current);
        sort($names);
        sort($favoriteLanguages);

        if ($names !== $favoriteLanguages) {
            $allowedIds = PostAllowedValue::whereIn('type', ['language', 'technology'])->whereIn('name', $favoriteLanguages)->pluck('id')->toArray();
            sort($allowedIds);
            sort($current);

            $removeLanguages = array_diff($current, $allowedIds);
            $addLanguages = array_diff($allowedIds, $current);

            if (count($allowedIds) !== count($favoriteLanguages)) {
                foreach ($favoriteLanguages as $langName) {
                    if (!in_array($langName, $names)) {
                        return $this->errorResponse("Language '$langName' is not allowed", 'FORBIDDEN_LANGUAGE', 422);
                    }
                }
            }

            $pivotTable = $userProfile->favoriteLanguages()->getTable();
            DB::table($pivotTable)->where('user_profile_id', $userProfile->id)->whereIn('post_allowed_value_id', $removeLanguages)->delete();


            if (!empty($addLanguages)) {
                $insertData = [];
                foreach ($addLanguages as $langId) {
                    $insertData[] = [
                        'user_profile_id' => $userProfile->id,
                        'post_allowed_value_id' => $langId,
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
