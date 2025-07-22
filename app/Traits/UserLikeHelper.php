<?php

namespace App\Traits;

use Illuminate\Http\Request;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

use App\Models\Post;
use App\Models\Comment;
use App\Models\User;
use App\Models\UserLike;

trait UserLikeHelper {

    /**
     * Check if the user can like the content
     * 
     * @param mixed $user The authenticated user
     * @param string $likeableType The type of entity to like (Post or Comment)
     * @param mixed $likeable The likeable entity
     * @param int $likeableId The ID of the entity to like
     * @param string $simpleType The simple type of the entity (post or comment)
     * @return JsonResponse|null
     * 
     * @example | $likeableResult = $this->checkIfUserCanLike($user, $likeableType, $likeable, $likeableId, $simpleType);
     *            if ($likeableResult !== null) {
     *              return $likeableResult;
     *            }
     */
    private function checkIfUserCanLike($user, $likeableType, $likeable, $likeableId, $simpleType) {
        if ($likeableType === Post::class && $likeable->user_id == $user->id) {
            return $this->errorResponse('You cannot like your own post', 'CANNOT_LIKE_OWN_POST', 403);
        } else if ($likeableType === Comment::class && $likeable->user_id == $user->id) {
            return $this->errorResponse('You cannot like your own comment', 'CANNOT_LIKE_OWN_COMMENT', 403);
        }

        $existingLike = UserLike::where(['user_id' => $user->id, 'likeable_id' => $likeableId, 'likeable_type' => $likeableType])->first();

        if ($existingLike) {
            return $this->errorResponse('You have already liked this ' . $simpleType, 'ALREADY_LIKED', 403);
        }
        return null;
    }


    /**
     * Check if entities are liked by the current user
     *
     * @param User|null $user
     * @param Builder|Collection|LengthAwarePaginator|Post|Comment $query
     * @param string $modelType 'post' or 'comment'
     * @return mixed
     * 
     * @example | $isLiked = $this->isLiked($user, $query, 'post');
     */
    public function isLiked(Request $request, $user, $query, string $modelType, $originalSelectFields): Builder|Collection|LengthAwarePaginator|Post|Comment {

        /**
         * If the request does not have 'select' or if 'is_liked' is included in 'select',
         * we will check if the authenticated user has liked the post or comment in the query.
         * and we will add the 'is_liked' field to the post or comment relation.
         */
        if (!$request->has('select') || in_array('is_liked', $originalSelectFields)) {
            $className = $modelType === 'post' ? Post::class : Comment::class;

            if ($query instanceof $className) {
                if (!$user) {
                    $query->is_liked = false;
                    return $query;
                }

                $query->is_liked = $user->likes()->where('likeable_type', $className)->where('likeable_id', $query->id)->exists();

                return $query;
            }

            $ids = $query->pluck('id')->toArray();
            $likedItems = [];

            if ($user && !empty($ids)) {
                $likedItems = $user->likes()->where('likeable_type', $className)->whereIn('likeable_id', $ids)->pluck('likeable_id')->toArray();
            }

            $query->each(function ($item) use ($likedItems) {
                $item->is_liked = in_array($item->id, $likedItems);
            });

            return $query;
        }
        return $query;
    }
}
