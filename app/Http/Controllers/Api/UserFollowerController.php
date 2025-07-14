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
use App\Traits\FieldManager;
use App\Traits\UserFollowerHelper;

use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserFollowerController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, ApiInclude, QueryBuilder, RelationLoader, FieldManager, UserFollowerHelper;

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

        // These relationships are loaded unconditionally as they're needed for internal logic
        $query = $this->loadRelations($request, $query, [
            ['relation' => 'follower', 'foreignKey' => 'follower_id', 'columns' => $this->getRelationFieldsFromRequest($request, 'follower', [], ['id', 'display_name', 'role', 'created_at', 'updated_at', 'is_banned', 'was_ever_banned', 'moderation_info'])],
            ['relation' => 'user', 'foreignKey' => 'user_id', 'columns' => $this->getRelationFieldsFromRequest($request, 'user', [], ['id', 'display_name', 'role', 'created_at', 'updated_at', 'is_banned', 'was_ever_banned', 'moderation_info'])],
        ]);

        $query = $this->buildQuery($request, $query, 'user_followers');

        return $query;
    }

    /**
     * Get User's Followers
     * 
     * Endpoint: GET /followers
     *
     * Retrieves a paginated list of users who follow the authenticated user.
     * Also indicates if the authenticated user follows back each follower (mutual follow).
     *
     * @group Users - Followers
     *
     * @queryParam select   See [ApiSelectable](#apiselectable) for field selection details.
     * @see \App\Traits\ApiSelectable::select()
     * 
     * @queryParam sort     See [ApiSorting](#apisorting) for sorting details.
     * @see \App\Traits\ApiSorting::sort()
     * 
     * @queryParam filter   See [ApiFiltering](#apifiltering) for filtering details.
     * @see \App\Traits\ApiFiltering::filter()
     * 
     * @queryParam include  See [ApiInclude](#apiinclude) for relation inclusion details (e.g. user, follower).
     * @see \App\Traits\ApiInclude::getRelationKeyFields()
     * 
     * @queryParam *_fields string See [ApiInclude](#apiinclude). When including a relation, specify fields to return. Example: follower_fields=id,display_name
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest()
     * 
     * @queryParam page     Pagination, see [ApiPagination](#apipagination).
     * @see \App\Traits\ApiPagination::paginate()
     * 
     * @queryParam per_page Pagination, see [ApiPagination](#apipagination).
     * @see \App\Traits\ApiPagination::paginate()
     * 
     * @queryParam setLimit Disables pagination and limits the number of results. See [ApiLimit](#apilimit).
     * @see \App\Traits\ApiLimit::setLimit()
     *
     * Example URL: /followers
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Followers retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 4,
     *       "user_id": 1,
     *       "follower_id": 21,
     *       "created_at": "2025-07-09T16:37:32.000000Z",
     *       "updated_at": "2025-07-09T16:37:32.000000Z",
     *       "is_following_back": true                      || Virtual field: true if the authenticated user follows this follower
     *     }
     *   ]
     * }
     * 
     * Example URL: /followers/?include=user,follower
     *
     * @response status=200 scenario="Success (with includes)" {
     *   "status": "success",
     *   "message": "Followers retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 4,
     *       "user_id": 1,
     *       "follower_id": 21,
     *       "created_at": "2025-07-09T16:37:32.000000Z",
     *       "updated_at": "2025-07-09T16:37:32.000000Z",
     *       "is_following_back": true,                     || Virtual field: true if the authenticated user follows this follower
     *       "follower": {
     *         "id": 21,
     *         "display_name": "Maxi21",
     *         "role": "user",
     *         "created_at": "2025-07-05T21:38:52.000000Z",
     *         "updated_at": "2025-07-05T21:38:52.000000Z",
     *         "is_banned": null,                           || Admin and Moderator only
     *         "was_ever_banned": false,                    || Admin and Moderator only
     *         "moderation_info": []                        || Admin and Moderator only
     *       },
     *       "user": {
     *         "id": 1,
     *         "display_name": "Admin",
     *         "role": "admin",
     *         "created_at": "2025-07-05T21:38:51.000000Z",
     *         "updated_at": "2025-07-05T21:38:51.000000Z",
     *         "is_banned": null,                           || Admin and Moderator only
     *         "was_ever_banned": false,                    || Admin and Moderator only
     *         "moderation_info": []                        || Admin and Moderator only
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
     * Note: The "is_following_back" field is a virtual field and indicates if the authenticated user follows back the follower (mutual follow relationship).
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

            $query = $this->manageUsersFieldVisibility($request, $query);

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
     * Retrieves a paginated list of users that the authenticated user follows.
     * Also indicates if those users follow back the authenticated user (mutual follow).
     *
     * @group Users - Followers
     *
     * @queryParam select   See [ApiSelectable](#apiselectable) for field selection details.
     * @see \App\Traits\ApiSelectable::select()
     * 
     * @queryParam sort     See [ApiSorting](#apisorting) for sorting details.
     * @see \App\Traits\ApiSorting::sort()
     * 
     * @queryParam filter   See [ApiFiltering](#apifiltering) for filtering details.
     * @see \App\Traits\ApiFiltering::filter()
     * 
     * @queryParam include  See [ApiInclude](#apiinclude) for relation inclusion details (e.g. user, follower).
     * @see \App\Traits\ApiInclude::getRelationKeyFields()
     * 
     * @queryParam *_fields string See [ApiInclude](#apiinclude). When including a relation, specify fields to return. Example: user_fields=id,display_name
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest()
     * 
     * @queryParam page     Pagination, see [ApiPagination](#apipagination).
     * @see \App\Traits\ApiPagination::paginate()
     * 
     * @queryParam per_page Pagination, see [ApiPagination](#apipagination).
     * @see \App\Traits\ApiPagination::paginate()
     * 
     * @queryParam setLimit Disables pagination and limits the number of results. See [ApiLimit](#apilimit).
     * @see \App\Traits\ApiLimit::setLimit()
     *
     * Example URL: /following
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Following retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 5,
     *       "follower_id": 1,
     *       "created_at": "2025-07-09T16:37:06.000000Z",
     *       "updated_at": "2025-07-09T16:37:06.000000Z",
     *       "is_following_back": false // Virtual field: true if the user being followed also follows the authenticated user
     *     }
     *   ]
     * }
     * 
     * Example URL: /following/?include=follower,user
     *
     * @response status=200 scenario="Success (with includes)" {
     *   "status": "success",
     *   "message": "Following retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 5,
     *       "follower_id": 1,
     *       "created_at": "2025-07-09T16:37:06.000000Z",
     *       "updated_at": "2025-07-09T16:37:06.000000Z",
     *       "is_following_back": false,                                || Virtual field: true if the user being followed also follows the authenticated user
     *       "follower": {
     *         "id": 1,
     *         "display_name": "Admin",
     *         "role": "admin",
     *         "created_at": "2025-07-05T21:38:51.000000Z",
     *         "updated_at": "2025-07-05T21:38:51.000000Z",
     *         "is_banned": null,                                       || Admin and Moderator only
     *         "was_ever_banned": false,                                || Admin and Moderator only
     *         "moderation_info": []                                    || Admin and Moderator only
     *       },
     *       "user": {
     *         "id": 5,
     *         "display_name": "Moderator",
     *         "role": "moderator",
     *         "created_at": "2025-07-05T21:38:52.000000Z",
     *         "updated_at": "2025-07-05T21:38:52.000000Z",
     *         "is_banned": null,                                       || Admin and Moderator only
     *         "was_ever_banned": false,                                || Admin and Moderator only
     *         "moderation_info": []                                    || Admin and Moderator only
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
     * Note: The "is_following_back" field is a virtual field and indicates if the user being followed also follows the authenticated user (mutual follow relationship).
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

            $query = $this->manageUsersFieldVisibility($request, $query);

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
     * @urlParam userId integer required The ID of the user to unfollow. Example: 5
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
