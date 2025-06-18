<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

use App\Models\Post;

use App\Traits\AccessFilter;
use App\Traits\ApiSelectable;

use function PHPUnit\Framework\isEmpty;

/**
 * Trait for setting up post queries with access filters and relations.
 * This trait is used to set up the query for the post, applying access filters,
 * loading relations, and modifying the request select fields.
 */
trait PostQuerySetup {

    /**
     *  The traits used in the controller
     */
    use AccessFilter, ApiSelectable;

    /**
     * Setup the post query
     * This method is used to set up the query for the post
     * It applies the access filters.
     * It applies sorting, filtering, selecting, and pagination
     * It also loads the relations for the post
     * 
     * @param Request $request
     * @param $query
     * @param $methods (string) The method to call for building the query
     * @return mixed
     * 
     * @example | $query = $this->setupPostQuery($request, $query, 'buildQuery');
     */
    protected function setupPostQuery(Request $request, $query, string $methods): mixed {

        $query = $this->applyPostAccessFilters($request, $query);

        $relationKeyFields = $this->getRelationKeyFields($request, ['user' => 'user_id']);

        $this->modifyRequestSelect($request, [...['id'], ...$relationKeyFields]);

        $query = $this->loadUserRelation($request, $query);

        $query = $this->loadTagsRelation($request, $query);

        $query = $this->$methods($request, $query, 'post');
        if ($query instanceof JsonResponse) {
            return $query;
        }

        return $query;
    }


    /**
     * Load the user relation
     * 
     * @param Request $request
     * @param mixed $query Builder|LengthAwarePaginator|Collection
     * @return mixed Builder|LengthAwarePaginator|Collection
     * 
     * @example | $this->loadUserRelation($request, $query)
     */
    private function loadUserRelation(Request $request, $query): mixed {
        if ($request->has('include') && in_array('user', explode(',', $request->input('include')))) {
            $query = $this->loadRelations($request, $query, [
                ['relation' => 'user', 'foreignKey' => 'user_id', 'columns' => $this->getRelationFieldsFromRequest($request, 'user', [], ['id', 'display_name', 'role', 'created_at', 'updated_at', 'is_banned', 'was_ever_banned', 'moderation_info'])],
            ]);
        }
        return $query;
    }

    /**
     * Load the tags relation
     * 
     * @param Request $request
     * @param mixed $query Builder|LengthAwarePaginator|Collection
     * @return mixed Builder|LengthAwarePaginator|Collection
     * 
     * @example | $this->loadTagsRelation($request, $query)
     */
    private function loadTagsRelation(Request $request, $query): mixed {

        /**
         * If the request does not have the 'select' parameter or if 'tags' is selected,
         * we will load the tags relation by default.
         */
        if (!$request->has('select') || $this->isSelected($request, 'tags')) {

            /**
             * If tags have been set in the request, we remove it from the select fields
             */
            $this->removeFromSelect($request, 'tags');

            /**
             * Load the tags relation by Default
             * 
             * Explicit table.column AS alias format is used for many-to-many relationships
             * This is to avoid ambiguity in the result set, especially when joining multiple tables.
             */
            $tableName = $query->getModel()->tags()->getRelated()->getTable(); // Get the table name of the tags relation

            $defaultColumns = [
                "$tableName.id as id",
                "$tableName.name as name"
            ];

            $query = $this->loadRelations($request, $query, [
                ['relation' => 'tags', 'foreignKey' => 'id', 'columns' => $defaultColumns],
            ]);

            return $query;
        }

        return $query;
    }


    /**
     * Check if a post is favorited by a user
     *
     * @param User|null $user
     * @param Builder|Collection|LengthAwarePaginator|Post $query
     * @return Builder|Collection|LengthAwarePaginator|Post
     * 
     * @example | $this->postRelationService->isFavorited($user, $query);
     */
    public function isFavorited($user, $query): Builder|Collection|LengthAwarePaginator|Post {
        if ($query instanceof Post) {
            if (!$user) {
                $query->is_favorited = false;
                return $query;
            }
            $query->is_favorited = $user->favorites()
                ->where('post_id', $query->id)
                ->exists();
            return $query;
        }

        $postIds = $query->pluck('id')->toArray();
        $favoritedPosts = [];

        if ($user && !empty($postIds)) {
            $favoritedPosts = $user->favorites()
                ->whereIn('post_id', $postIds)
                ->pluck('post_id')
                ->toArray();
        }

        $query->each(function ($post) use ($favoritedPosts) {
            $post->is_favorited = in_array($post->id, $favoritedPosts);
        });

        return $query;
    }
}
