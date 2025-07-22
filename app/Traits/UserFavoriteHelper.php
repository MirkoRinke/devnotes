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
}
