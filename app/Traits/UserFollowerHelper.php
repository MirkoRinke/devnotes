<?php

namespace App\Traits;

use Illuminate\Http\Request;

use App\Models\Post;
use App\Models\Comment;
use App\Models\UserProfile;
use App\Models\UserFollower;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

use Illuminate\Database\Eloquent\Model;

use App\Traits\AuthHelper;


trait UserFollowerHelper {

    /**
     *  The traits used in the trait
     */
    use AuthHelper;

    /**
     * Add mutual follow status to the query
     * 
     * This method checks if the authenticated user follows back the followers or users they are following.
     * It adds an "is_following_back" field to the query results.
     * 
     * @param Request $request The current HTTP request
     * @param mixed $query The query builder instance
     * @param User $user The authenticated user
     * @param array $originalSelectFields The original select fields from the request
     * @param string $relation The relation type (followers or following)
     * 
     * @return mixed The modified query with mutual follow status
     * 
     * @example | $query = $this->addMutualFollowStatus($request, $query, $user, $originalSelectFields, 'followers');
     */
    private function addMutualFollowStatus(Request $request, $query, $user, $originalSelectFields, string $relation): mixed {
        if (!$request->has('select')  || $request->has('select') && in_array('is_following_back', $originalSelectFields)) {

            $idField = $relation === 'followers' ? 'follower_id' : 'user_id';
            $relationField = $relation === 'followers' ? 'user_id' : 'follower_id';

            $followerIds = $query->pluck($idField);

            $mutualFollows = UserFollower::where($idField, $user->id)
                ->whereIn($relationField, $followerIds)
                ->pluck($relationField)
                ->flip()
                ->toArray();

            $query->each(function ($record) use ($mutualFollows, $relation) {
                $idToCheck = $relation === 'followers' ? $record->follower_id : $record->user_id;
                $record->is_following_back = isset($mutualFollows[$idToCheck]);
            });
        }
        return $query;
    }

    /**
     * Check if the user is following the user in the query
     *
     * @param $request
     * @param Builder|Collection|LengthAwarePaginator|Post|Comment|UserProfile $query
     * @return Builder|Collection|LengthAwarePaginator|Post|Comment|UserProfile
     */
    public function isFollowing($request, $query): Builder|Collection|LengthAwarePaginator|Post|Comment|UserProfile {
        if ($request->has('include') && in_array('user', explode(',', $request->input('include')))) {

            /**
             * If the request does not have 'user_fields' or if 'is_following' is included in 'user_fields',
             * we will check if the authenticated user is following the user in the query.
             * and we will add the 'is_following' field to the user relation.
             */
            if (!$request->has('user_fields') || in_array('is_following', explode(',', $request->input('user_fields')))) {

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
        return $query;
    }
}
