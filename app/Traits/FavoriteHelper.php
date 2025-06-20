<?php

namespace App\Traits;

use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

trait FavoriteHelper {

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
