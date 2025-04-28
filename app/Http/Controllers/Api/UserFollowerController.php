<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use App\Models\User;
use App\Models\UserFollower;

use App\Traits\ApiResponses;
use App\Traits\ApiInclude;
use App\Traits\QueryBuilder;
use App\Traits\RelationLoader;

use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserFollowerController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, ApiInclude, QueryBuilder, RelationLoader, AuthorizesRequests;


    /**
     * Setup the query for followers
     */
    protected function setupFollowerQuery(Request $request, $query) {
        $this->modifyRequestSelect($request, ['id', 'user_id', 'follower_id'], ['is_followed']);

        $query = $this->loadRelations($request, $query, [
            ['relation' => 'follower', 'foreignKey' => 'follower_id', 'columns' => ['id', 'name']],
            ['relation' => 'user', 'foreignKey' => 'user_id', 'columns' => ['id', 'name']]
        ]);

        $query = $this->buildQuery($request, $query, 'user_followers');

        return $query;
    }


    /**
     * Get all followers for the authenticated user
     */
    public function getFollowers(Request $request): JsonResponse {
        $user = $request->user();

        $query = UserFollower::where('user_id', $user->id);

        $originalSelectFields = $this->getSelectFields($request);

        $query = $this->setupFollowerQuery($request, $query);


        if ($query instanceof JsonResponse) {
            return $query;
        }

        if ($query->isEmpty()) {
            return $this->successResponse($query, 'No followers found', 200);
        }

        $followerIds = $query->pluck('follower_id');

        $mutualFollows = UserFollower::where('follower_id', $user->id)
            ->whereIn('user_id', $followerIds)
            ->pluck('user_id')
            ->flip()
            ->toArray();

        $query->each(function ($follower) use ($mutualFollows) {
            $follower->is_followed = isset($mutualFollows[$follower->follower->id]);
        });


        $query = $this->checkForIncludedRelations($request, $query);
        $query = $this->controlVisibleFields($request, $originalSelectFields, $query);

        return $this->successResponse($query, 'Followers retrieved successfully', 200);
    }

    /**
     * Get all users the authenticated user is following
     */
    public function getFollowing(Request $request): JsonResponse {
        $user = $request->user();

        $query = UserFollower::where('follower_id', $user->id);

        $originalSelectFields = $this->getSelectFields($request);

        $query = $this->setupFollowerQuery($request, $query);

        if ($query instanceof JsonResponse) {
            return $query;
        }

        if ($query->isEmpty()) {
            return $this->successResponse($query, 'Not following any users', 200);
        }

        $followingIds = $query->pluck('user_id');

        $mutualFollows = UserFollower::where('user_id', $user->id)
            ->whereIn('follower_id', $followingIds)
            ->pluck('follower_id')
            ->flip()
            ->toArray();

        $query->each(function ($follow) use ($mutualFollows) {
            $follow->is_followed = isset($mutualFollows[$follow->user->id]);
        });


        $query = $this->checkForIncludedRelations($request, $query);
        $query = $this->controlVisibleFields($request, $originalSelectFields, $query);

        return $this->successResponse($query, 'Following retrieved successfully', 200);
    }

    /**
     * Follow a user
     */
    public function follow(Request $request, $userId): JsonResponse {
        try {
            $follower = $request->user();
            $userToFollow = User::findOrFail($userId);

            // You can not follow yourself
            if ($follower->id === $userToFollow->id) {
                return $this->errorResponse('Cannot follow yourself', 'CANNOT_FOLLOW_SELF', 400);
            }

            // Check if the user is already followed
            $exists = UserFollower::where('user_id', $userToFollow->id)->where('follower_id', $follower->id)->exists();

            if ($exists) {
                $follow = UserFollower::where('user_id', $userToFollow->id)->where('follower_id', $follower->id)->first();
                return $this->successResponse($follow, 'Already following this user', 200);
            }

            $follow = UserFollower::create([
                'user_id' => $userToFollow->id,
                'follower_id' => $follower->id
            ]);

            return $this->successResponse($follow, 'User followed successfully', 201);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('User not found', 'USER_NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Unfollow a user
     */
    public function unfollow(Request $request, $userId): JsonResponse {
        try {
            $follower = $request->user();
            $userToUnfollow = User::findOrFail($userId);

            $follow = UserFollower::where('user_id', $userToUnfollow->id)->where('follower_id', $follower->id)->first();

            if (!$follow) {
                return $this->errorResponse('Not following this user', 'NOT_FOLLOWING', 404);
            }

            $follow->delete();

            return $this->successResponse(null, 'User unfollowed successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('User not found', 'USER_NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
