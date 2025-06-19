<?php

namespace App\Traits;

use App\Models\Post;
use App\Models\Comment;
use App\Models\UserProfile;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

use Illuminate\Database\Eloquent\Model;

use App\Traits\AuthHelper;


trait FollowerHelper {

    /**
     *  The traits used in the trait
     */
    use AuthHelper;

    /**
     * Check if the user is following the user in the query
     *
     * @param $request
     * @param Builder|Collection|LengthAwarePaginator|Post|Comment|UserProfile $query
     * @return Builder|Collection|LengthAwarePaginator|Post|Comment|UserProfile
     */
    public function isFollowing($request, $query): Builder|Collection|LengthAwarePaginator|Post|Comment|UserProfile {
        if ($request->has('include') && in_array('user', explode(',', $request->input('include')))) {
            $user = $this->getAuthenticatedUser($request);

            if ($query instanceof Model) {
                if (!$query->relationLoaded('user') || !$query->user) {
                    return $query;
                }

                if (!$user) {
                    $query->user->is_following = false;
                    return $query;
                }

                $query->user->is_following = $user->followingRelations()
                    ->where('user_id', $query->user->id)
                    ->exists();

                return $query;
            }

            $userIds = [];

            $query->each(function ($item) use (&$userIds) {
                if ($item->relationLoaded('user') && $item->user) {
                    $userIds[] = $item->user->id;
                }
            });

            $followedIds = [];
            if ($user && !empty($userIds)) {
                $followedIds = $user->followingRelations()
                    ->whereIn('user_id', array_unique($userIds))
                    ->pluck('user_id')
                    ->toArray();
            }

            $query->each(function ($item) use ($followedIds) {
                if ($item->relationLoaded('user') && $item->user) {
                    $item->user->is_following = in_array($item->user->id, $followedIds);
                }
            });

            return $query;
        }
        return $query;
    }
}
