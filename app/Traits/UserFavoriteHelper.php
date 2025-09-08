<?php

namespace App\Traits;

use Illuminate\Http\Request;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

use App\Models\Post;

use App\Traits\ApiSelectable;

trait UserFavoriteHelper {

    /**
     *  The traits used in the Trait
     */
    use ApiSelectable;

    /**
     * Check if a post is favorited by a user
     *
     * @param Request $request
     * @param User|null $user
     * @param Builder|Collection|LengthAwarePaginator|Post $query
     * @param array $originalSelectFields
     * @return Builder|Collection|LengthAwarePaginator|Post
     * 
     * @example | $this->postRelationService->isFavorited($request, $user, $query, $originalSelectFields);
     */
    public function isFavorited(Request $request, $user, $query, $originalSelectFields): Builder|Collection|LengthAwarePaginator|Post {
        /**
         * If the request does not have 'select' or if 'is_favorited' is included in 'select',
         * we will check if the authenticated user has favorited the post in the query.
         * and we will add the 'is_favorited' field to the post relation.
         */
        if (!$request->has('select') || in_array('is_favorited', $originalSelectFields)) {
            if ($query instanceof Post) {
                if (!$user) {
                    $query->is_favorited = false;
                    return $query;
                }
                $query->is_favorited = $user->favorites()->where('post_id', $query->id)->exists();

                return $query;
            }

            $postIds = $query->pluck('id')->toArray();
            $favoritedPosts = [];

            if ($user && !empty($postIds)) {
                $favoritedPosts = $user->favorites()->whereIn('post_id', $postIds)->pluck('post_id')->toArray();
            }

            $query->each(function ($post) use ($favoritedPosts) {
                $post->is_favorited = in_array($post->id, $favoritedPosts);
            });

            return $query;
        }
        return $query;
    }


    /**
     * Determine if the authenticated user has marked the posts as important favorites.
     *
     * This method checks if the authenticated user has marked each post in the query as an important favorite.
     * It adds an 'is_important' attribute to each post (true/false).
     * If the request does not specify a 'select' parameter or if 'is_important' is included in the original select fields,
     * the attribute will be set; otherwise, the query is returned unchanged.
     *
     * @param Request $request The incoming HTTP request.
     * @param mixed $user The authenticated user (or null if not authenticated).
     * @param Builder|Collection|LengthAwarePaginator|Post $query The query or collection of posts to check.
     * @param array $originalSelectFields The original fields requested in the 'select' parameter.
     * @param bool $important If true, all posts will be marked as important without database checks. We only have important posts in the query.
     *
     * @return Builder|Collection|LengthAwarePaginator|Post The modified query or collection with 'is_important' attributes added.
     *
     * @example $posts = $this->isImportant($request, $user, $posts, $originalSelectFields);
     */
    public function isImportant(Request $request, $user, $query, $originalSelectFields, bool $important =  false): Builder|Collection|LengthAwarePaginator|Post {
        if (!$request->has('select') || in_array('is_important', $originalSelectFields)) {
            if ($query instanceof Post) {
                if (!$user) {
                    $query->is_important = false;
                    return $query;
                }

                if ($important) {
                    $query->is_important = true;
                    return $query;
                }

                $query->is_important = $user->favorites()->where('post_id', $query->id)->where('is_important', true)->exists();

                return $query;
            }

            if ($important) {
                $query->each(function ($post) {
                    $post->is_important = true;
                });
                return $query;
            }

            $postIds = $query->pluck('id')->toArray();
            $importantPosts = [];

            if ($user && !empty($postIds)) {
                $importantPosts = $user->favorites()->whereIn('post_id', $postIds)->where('is_important', true)->pluck('post_id')->toArray();
            }

            $query->each(function ($post) use ($importantPosts) {
                $post->is_important = in_array($post->id, $importantPosts);
            });

            return $query;
        }
        return $query;
    }
}
