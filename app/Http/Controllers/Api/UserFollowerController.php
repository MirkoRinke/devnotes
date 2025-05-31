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

class UserFollowerController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, ApiInclude, QueryBuilder, RelationLoader;

    /**
     * Setup the query for followers
     * 
     * @param Request $request
     * @param mixed $query
     * @return mixed The modified query
     * 
     * @example | $query = $this->setupFollowerQuery($request, $query);
     */
    protected function setupFollowerQuery(Request $request, $query) {
        $this->modifyRequestSelect($request, ['id', 'user_id', 'follower_id'], ['is_following_back']);

        $query = $this->loadRelations($request, $query, [
            ['relation' => 'follower', 'foreignKey' => 'follower_id', 'columns' => $this->getRelationFieldsFromRequest($request, 'follower', [], ['id', 'display_name', 'role', 'created_at', 'updated_at', 'is_banned', 'was_ever_banned', 'moderation_info'])],
            ['relation' => 'user', 'foreignKey' => 'user_id', 'columns' => $this->getRelationFieldsFromRequest($request, 'user', [], ['id', 'display_name', 'role', 'created_at', 'updated_at', 'is_banned', 'was_ever_banned', 'moderation_info'])],
        ]);

        $query = $this->buildQuery($request, $query, 'user_followers');

        return $query;
    }

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
     * Get User's Followers
     * 
     * Endpoint: GET /followers
     *
     * Retrieves a list of users who follow the authenticated user.
     * Also indicates if the authenticated user follows back each follower (mutual follow).
     *
     * @group Users - Followers
     *
     * @queryParam select string Optional. Select specific fields. Example: select=id,follower_id
     * @queryParam sort string Optional. Sort by fields (prefix with - for descending). Example: sort=-created_at
     * @queryParam filter string Optional. Filter by fields (prefix with - for descending). Example: filter=[is_following_back]=true
     * 
     * @queryParam startsWith[name] string Optional. Filter records where a field starts with a specific value. Format: field:value. Example: startsWith[created_at]:2025-05
     * @queryParam endsWith[email] string Optional. Filter records where a field ends with a specific value. Format: field:value. Example: endsWith:[created_at]:Z
     * 
     * @queryParam page int Optional. Page number for pagination. Example: page=1
     * @queryParam per_page int Optional. Items per page for pagination. Example: per_page=10 (default: 15)
     * 
     * @queryParam include string Optional. Include related resources: follower, user. Example: include=user,follower
     * @queryParam user_fields string When including user relation, specify fields to return. 
     *                              Available fields: id, name, display_name, role, created_at, updated_at
     *                              Example: user_fields=id,name,display_name
     * @queryParam follower_fields string When including follower relation, specify fields to return.
     *                              Available fields: id, name, display_name, role, created_at, updated_at
     *                              Example: follower_fields=id,name,display_name
     * 
     * Example URL: /followers
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Followers retrieved successfully",
     *   "code": 200,
     *   "count": 2,
     *   "data": [
     *     {
     *       "id": 3,
     *       "user_id": 1,
     *       "follower_id": 6,
     *       "created_at": "2025-05-06T17:23:24.000000Z",
     *       "updated_at": "2025-05-06T17:23:24.000000Z",
     *       "is_following_back": false
     *     },
     *     {
     *       "id": 4,
     *       "user_id": 1,
     *       "follower_id": 7,
     *       "created_at": "2025-05-06T17:23:32.000000Z",
     *       "updated_at": "2025-05-06T17:23:32.000000Z",
     *       "is_following_back": false
     *     }
     *   ]
     * }
     * 
     * Example URL: /followers/?include=user,follower
     * 
     * @response status=200 scenario="Success with Included User and Follower Data" {
     *   "status": "success",
     *   "message": "Followers retrieved successfully",
     *   "code": 200,
     *   "count": 2,
     *   "data": [
     *     {
     *       "id": 3,
     *       "user_id": 1,
     *       "follower_id": 6,
     *       "created_at": "2025-05-06T17:23:24.000000Z",
     *       "updated_at": "2025-05-06T17:23:24.000000Z",
     *       "is_following_back": false,
     *       "follower": {
     *         "id": 6,
     *         "name": "Max Mustermann6"
     *       }
     *       "user": {
     *        "id": 1,
     *        "name": "Max Mustermann1"
     *      }
     *     },
     *     {
     *       "id": 4,
     *       "user_id": 1,
     *       "follower_id": 7,
     *       "created_at": "2025-05-06T17:23:32.000000Z",
     *       "updated_at": "2025-05-06T17:23:32.000000Z",
     *       "is_following_back": false,
     *       "follower": {
     *         "id": 7,
     *         "name": "Max Mustermann7"
     *       }
     *       "user": {
     *         "id": 1,
     *         "name": "Max Mustermann1"
     *       }
     *     }
     *   ]
     * }
     *
     * @response status=200 scenario="No Followers" {
     *   "status": "success",
     *   "message": "No followers found",
     *   "code": 200,
     *   "count": 0,
     *   "data": []
     * }
     *
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     * 
     * Note: The "is_following_back" field indicates if the authenticated user 
     * follows back the follower (mutual follow relationship).
     * 
     * @authenticated
     */
    public function getFollowers(Request $request): JsonResponse {
        try {
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

            $query = $this->addMutualFollowStatus($request, $query, $user, $originalSelectFields, 'followers');

            $query = $this->checkForIncludedRelations($request, $query);
            $query = $this->controlVisibleFields($request, $originalSelectFields, $query);

            return $this->successResponse($query, 'Followers retrieved successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('User not found', 'USER_NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Get Users the Authenticated User is Following
     * 
     * Endpoint: GET /following
     *
     * Retrieves a list of users that the authenticated user follows.
     * Also indicates if those users follow back the authenticated user (mutual follow).
     *
     * @group Users - Followers
     *
     * @queryParam select string Optional. Select specific fields. Example: select=id,follower_id
     * @queryParam sort string Optional. Sort by fields (prefix with - for descending). Example: sort=-created_at
     * @queryParam filter string Optional. Filter by fields (prefix with - for descending). Example: filter=[is_following_back]=true
     * 
     * @queryParam startsWith[name] string Optional. Filter records where a field starts with a specific value. Format: field:value. Example: startsWith[created_at]:2025-05
     * @queryParam endsWith[email] string Optional. Filter records where a field ends with a specific value. Format: field:value. Example: endsWith:[created_at]:Z
     * 
     * @queryParam page int Optional. Page number for pagination. Example: page=1
     * @queryParam per_page int Optional. Items per page for pagination. Example: per_page=10 (default: 15)
     * 
     * @queryParam include string Optional. Include related resources: follower, user. Example: include=user,follower
     * @queryParam user_fields string When including user relation, specify fields to return. 
     *                              Available fields: id, name, display_name, role, created_at, updated_at
     *                              Example: user_fields=id,name,display_name
     * @queryParam follower_fields string When including follower relation, specify fields to return.
     *                              Available fields: id, name, display_name, role, created_at, updated_at
     *                              Example: follower_fields=id,name,display_name
     * 
     * Example URL: /following
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Following retrieved successfully",
     *   "code": 200,
     *   "count": 2,
     *   "data": [
     *     {
     *       "id": 5,
     *       "user_id": 8,
     *       "follower_id": 1,
     *       "created_at": "2025-05-06T17:24:15.000000Z",
     *       "updated_at": "2025-05-06T17:24:15.000000Z",
     *       "is_following_back": true
     *     },
     *     {
     *       "id": 6,
     *       "user_id": 9,
     *       "follower_id": 1,
     *       "created_at": "2025-05-06T17:24:22.000000Z",
     *       "updated_at": "2025-05-06T17:24:22.000000Z",
     *       "is_following_back": false
     *     }
     *   ]
     * }
     * 
     * Example URL: /following/?include=user,follower
     * 
     * @response status=200 scenario="Success with Included User and Follower Data" {
     *   "status": "success",
     *   "message": "Following retrieved successfully",
     *   "code": 200,
     *   "count": 2,
     *   "data": [
     *     {
     *       "id": 5,
     *       "user_id": 8,
     *       "follower_id": 1,
     *       "created_at": "2025-05-06T17:24:15.000000Z",
     *       "updated_at": "2025-05-06T17:24:15.000000Z",
     *       "is_following_back": true,
     *       "follower": {
     *         "id": 1,
     *         "name": "Max Mustermann1"
     *       },
     *       "user": {
     *         "id": 8,
     *         "name": "Max Mustermann8"
     *       }
     *     },
     *     {
     *       "id": 6,
     *       "user_id": 9,
     *       "follower_id": 1,
     *       "created_at": "2025-05-06T17:24:22.000000Z",
     *       "updated_at": "2025-05-06T17:24:22.000000Z",
     *       "is_following_back": false,
     *       "follower": {
     *         "id": 1,
     *         "name": "Max Mustermann1"
     *       },
     *       "user": {
     *         "id": 9,
     *         "name": "Max Mustermann9"
     *       }
     *     }
     *   ]
     * }
     *
     * @response status=200 scenario="Not Following Anyone" {
     *   "status": "success",
     *   "message": "Not following any users",
     *   "code": 200,
     *   "count": 0,
     *   "data": []
     * }
     *
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     * 
     * Note: The "is_following_back" field indicates if the user being followed
     * also follows the authenticated user (mutual follow relationship).
     * 
     * @authenticated
     */
    public function getFollowing(Request $request): JsonResponse {
        try {
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

            $query = $this->addMutualFollowStatus($request, $query, $user, $originalSelectFields, 'following');

            $query = $this->checkForIncludedRelations($request, $query);
            $query = $this->controlVisibleFields($request, $originalSelectFields, $query);

            return $this->successResponse($query, 'Following retrieved successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('User not found', 'USER_NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Follow a User
     * 
     * Endpoint: POST /follow/{userId}
     *
     * Creates a follow relationship between the authenticated user and the specified user.
     * The authenticated user will become a follower of the target user.
     *
     * @group Users - Followers
     *
     * @urlParam userId required The ID of the user to follow. Example: 5
     * 
     * @response status=201 scenario="Success" {
     *   "status": "success",
     *   "message": "User followed successfully",
     *   "code": 201,
     *   "count": 1,
     *   "data": {
     *     "id": 7,
     *     "user_id": 5,
     *     "follower_id": 1,
     *     "created_at": "2025-05-06T18:12:45.000000Z",
     *     "updated_at": "2025-05-06T18:12:45.000000Z"
     *   }
     * }
     * 
     * @response status=200 scenario="Already Following" {
     *   "status": "success",
     *   "message": "Already following this user",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 7,
     *     "user_id": 5,
     *     "follower_id": 1,
     *     "created_at": "2025-05-06T18:12:45.000000Z",
     *     "updated_at": "2025-05-06T18:12:45.000000Z"
     *   }
     * }
     *
     * @response status=400 scenario="Cannot Follow Self" {
     *   "status": "error",
     *   "message": "Cannot follow yourself",
     *   "code": 400,
     *   "errors": "CANNOT_FOLLOW_SELF"
     * }
     *
     * @response status=404 scenario="User Not Found" {
     *   "status": "error",
     *   "message": "User not found",
     *   "code": 404,
     *   "errors": "USER_NOT_FOUND"
     * }
     *
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     * 
     * @authenticated
     */
    public function follow(Request $request, $userId): JsonResponse {
        try {
            $follower = $request->user();
            $userToFollow = User::findOrFail($userId);

            if ($follower->id === $userToFollow->id) {
                return $this->errorResponse('Cannot follow yourself', 'CANNOT_FOLLOW_SELF', 400);
            }

            // Check if the user is already followed
            $exists = UserFollower::where('user_id', $userToFollow->id)->where('follower_id', $follower->id)->exists();
            if ($exists) {
                $follow = UserFollower::where('user_id', $userToFollow->id)->where('follower_id', $follower->id)->first();
                return $this->successResponse($follow, 'Already following this user', 200);
            }

            // Create the follow relationship
            $follow = new UserFollower();

            $follow->user_id = $userToFollow->id;
            $follow->follower_id = $follower->id;

            $follow->save();

            return $this->successResponse($follow, 'User followed successfully', 201);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('User not found', 'USER_NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Unfollow a User
     * 
     * Endpoint: DELETE /unfollow/{userId}
     *
     * Removes a follow relationship between the authenticated user and the specified user.
     * The authenticated user will no longer follow the target user.
     *
     * @group Users - Followers
     *
     * @urlParam userId required The ID of the user to unfollow. Example: 5
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "User unfollowed successfully",
     *   "code": 200,
     *   "count": 0,
     *   "data": null
     * }
     * 
     * @response status=404 scenario="Not Following" {
     *   "status": "error",
     *   "message": "Not following this user",
     *   "code": 404,
     *   "errors": "NOT_FOLLOWING"
     * }
     *
     * @response status=404 scenario="User Not Found" {
     *   "status": "error",
     *   "message": "User not found",
     *   "code": 404,
     *   "errors": "USER_NOT_FOUND"
     * }
     *
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     * 
     * @authenticated
     */
    public function unfollow(Request $request, $userId): JsonResponse {
        try {
            $follower = $request->user();
            $userToUnfollow = User::findOrFail($userId);

            // Check if the user is not following
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
