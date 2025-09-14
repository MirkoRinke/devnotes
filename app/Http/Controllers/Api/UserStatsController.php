<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Post;

use App\Traits\ApiResponses;
use App\Traits\RelationLoader;
use App\Traits\ApiInclude;
use App\Traits\FieldManager;
use App\Traits\UserFollowerHelper;
use App\Traits\CacheHelper;

use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserStatsController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, RelationLoader, ApiInclude, FieldManager, UserFollowerHelper, CacheHelper;


    /**
     * Get Interactions for a User's Posts
     * 
     * Endpoint: GET /users/{user_id}/post-interactions
     *
     * Calculates the total count of likes, favorites, or combined interactions (sum of likes + favorites) for a user's posts.
     * This provides an aggregated view of how popular a user's content is.
     *
     * @group Users
     *
     * @urlParam user_id required The ID of the user to get interactions for. Example: 1
     * 
     * @queryParam type required The type of interaction to count. Must be either "likes_count", "favorite_count" or "interactions".
     * @queryParam period optional Filter results by time period. Available options:
     *   - Rolling time periods: subDay (last 24h), subWeek (last 7 days), subMonth (last 30 days), subYear (last 365 days)
     *   - Calendar periods: startOfDay (since 00:00 today), startOfWeek (since start of current week), 
     *     startOfMonth (since 1st of current month), startOfYear (since Jan 1st of current year)
     *   - If not specified, returns all-time statistics
     * 
     * Example URL: /users/1/post-interactions/?type=likes_count
     * Example URL with period: /users/1/post-interactions/?type=interactions&period=subWeek
     * Example URL with calendar period: /users/1/post-interactions/?type=likes_count&period=startOfMonth
     * 
     * @response status=200 scenario="Success (likes_count)" {
     *   "status": "success",
     *   "message": "Total likes_count for user with ID 1",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "user_id": 1,
     *     "count": 2
     *   }
     * }
     * 
     * Example URL: /users/1/post-interactions/?type=favorite_count
     *
     * @response status=200 scenario="Success (favorite_count)" {
     *   "status": "success",
     *   "message": "Total favorite_count for user with ID 1",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "user_id": 1,
     *     "count": 10
     *   }
     * }
     * 
     * Example URL: /users/1/post-interactions/?type=interactions
     * 
     * @response status=200 scenario="Success (interactions)" {
     *   "status": "success",
     *   "message": "Total interactions for user with ID 1",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "user_id": 1,
     *     "count": 12
     *   }
     * }
     * 
     * @response status=200 scenario="No posts found" {
     *   "status": "success",
     *   "message": "Total likes_count for user with ID 1",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "user_id": 1,
     *     "count": 12
     *   }
     * }
     * 
     * @response status=422 scenario="Validation Error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "type": ["TYPE_INVALID_OPTION"]
     *   }
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
    public function getUserPostsInteractions(Request $request, string $id) {
        try {
            $validatedData = $request->validate(
                [
                    'type' => 'required|string|in:likes_count,favorite_count,interactions',
                    'period' => 'sometimes|string|in:subDay,subWeek,subMonth,subYear,startOfDay,startOfWeek,startOfMonth,startOfYear'
                ],
                $this->getValidationMessages('PostInteractions')
            );

            $period = $validatedData['period'] ?? null;
            $type = $validatedData['type'];

            $cacheKey = $this->generateSimpleCacheKey('userPostsInteractions_' . $id . '_' . md5($this->generateCacheKeyWithSuffix($request, [$type, $period])));
            $cacheTTL = 150; // 2.5 minutes

            $total = $this->cacheData($cacheKey, $cacheTTL, function () use ($id, $period, $type) {
                $query = Post::where('user_id', $id);

                if ($period) {
                    $query->where('created_at', '>=', now()->{$period}());
                }

                if ($type === 'likes_count' || $type === 'favorite_count') {
                    $total = (int) $query->sum($type);
                }

                if ($type === 'interactions') {
                    $total = (int) $query->sum('likes_count') + $query->sum('favorite_count');
                }

                return $total ?? 0;
            });

            $result = [
                'user_id' => (int) $id,
                'count' => $total
            ];

            return $this->successResponse($result, 'Total ' . $validatedData['type'] . ' for user with ID ' . $id, 200);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Get Top Users By Post Interactions
     * 
     * Endpoint: GET /users/post-interactions
     *
     * Retrieves a list of users sorted by their post interactions (likes, favorites, or combined)
     * within a specified time period.
     * 
     * Caches the results for 1 hour to optimize performance, as this endpoint can be resource-intensive.
     *
     * @group Users
     * 
     * @queryParam type required The type of interaction to count. Must be either "likes_count", "favorite_count" or "interactions".
     * @queryParam period optional Filter results by time period. Available options:
     *   - Rolling time periods: subDay (last 24h), subWeek (last 7 days), subMonth (last 30 days), subYear (last 365 days)
     *   - Calendar periods: startOfDay (since 00:00 today), startOfWeek (since start of current week), 
     *     startOfMonth (since 1st of current month), startOfYear (since Jan 1st of current year)
     *   - If not specified, returns all-time statistics
     * @queryParam setLimit optional Number of users to return. Default: 10
     * @queryParam include optional Related resources to include. Available options: user
     * @queryParam user_fields optional Comma-separated list of user fields to include when loading the user relation.
     *   Example: id,display_name,avatar_items
     *
     * Example URL: /users/post-interactions?type=interactions&period=subMonth&setLimit=1
     *
     * @response status=200 scenario="Success (Basic)" {
     *   "status": "success",
     *   "message": "Top users by interactions",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "user_id": 419,
     *       "count": 10
     *     }
     *   ]
     * }
     *
     * Example URL with user relation: /users/post-interactions?type=interactions&period=subMonth&setLimit=1&include=user
     *
     * @response status=200 scenario="Success (With User Relation)" {
     *   "status": "success",
     *   "message": "Top users by interactions",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "user_id": 419,
     *       "count": 10,
     *       "user": {
     *         "id": 419,
     *         "display_name": "JohnDoe",
     *         "role": "user",
     *         "avatar_items": {
     *           "duck": null,
     *           "background": null,
     *           "ear_accessory": null,
     *           "eye_accessory": null,
     *           "head_accessory": null,
     *           "neck_accessory": null,
     *           "chest_accessory": null
     *         },
     *         "created_at": "2025-09-13T02:16:13.000000Z",
     *         "updated_at": "2025-09-13T02:16:51.000000Z",
     *         "is_banned": null,                               || Admin and Moderator only
     *         "was_ever_banned": false,                        || Admin and Moderator only 
     *         "moderation_info": [],                           || Admin and Moderator only
     *         "is_following": false                            || Virtual field, true if the authenticated user is following this user
     *       }
     *     }
     *   ]
     * }
     *
     * Example URL with specific user fields: /users/post-interactions?type=interactions&period=subMonth&setLimit=1&include=user&user_fields=id,display_name,avatar_items
     *
     * @response status=200 scenario="Success (With Filtered User Fields)" {
     *   "status": "success",
     *   "message": "Top users by interactions",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "user_id": 419,
     *       "count": 10,
     *       "user": {
     *         "id": 419,
     *         "display_name": "JohnDoe",
     *         "avatar_items": {
     *           "duck": null,
     *           "background": null,
     *           "ear_accessory": null,
     *           "eye_accessory": null,
     *           "head_accessory": null,
     *           "neck_accessory": null,
     *           "chest_accessory": null
     *         }
     *       }
     *     }
     *   ]
     * }
     * 
     * @response status=422 scenario="Validation Error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "type": ["TYPE_INVALID_OPTION"]
     *   }
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
    public function getTopUsersByPostInteractions(Request $request) {
        try {
            $validatedData = $request->validate(
                [
                    'type' => 'required|string|in:likes_count,favorite_count,interactions',
                    'period' => 'sometimes|string|in:subDay,subWeek,subMonth,subYear,startOfDay,startOfWeek,startOfMonth,startOfYear',
                    'setLimit' => 'sometimes|integer|min:1|max:100'
                ],
                $this->getValidationMessages('TopUsersByPostInteractions')
            );

            $period = $validatedData['period'] ?? null;
            $type = $validatedData['type'];
            $limit = $validatedData['setLimit'] ?? 10;

            $cacheKey = $this->generateSimpleCacheKey('topUsersByInteractions_' .  md5($this->generateCacheKeyWithSuffix($request, [$type, $period, $limit])));

            $cacheTTL = match ($period) {
                'subDay', 'startOfDay' => 3600,        // 1 hour
                'subWeek', 'startOfWeek' => 43200,     // 12 hours
                'subMonth', 'startOfMonth' => 86400,   // 1 day
                'subYear', 'startOfYear' => 172800,    // 2 days
                default => 3600,
            };

            $result = $this->cacheData($cacheKey, $cacheTTL, function () use ($period, $type, $limit, $request) {
                $query = Post::query();

                if ($period) {
                    $query->where('created_at', '>=', now()->{$period}());
                }

                $this->loadUserRelation($request, $query, 'user_id');

                if ($type === 'interactions') {
                    return $query->select('user_id', DB::raw('CAST(SUM(likes_count) + SUM(favorite_count) AS UNSIGNED) as count'))->groupBy('user_id')->orderBy('count', 'desc')->limit($limit)->get();
                } else {
                    return $query->select('user_id', DB::raw("CAST(SUM($type) AS UNSIGNED) as count"))->groupBy('user_id')->orderBy('count', 'desc')->limit($limit)->get();
                }
            });

            $result = $this->manageUsersFieldVisibility($request, $result);
            $result = $this->checkForIncludedRelations($request, $result);
            $result = $this->isFollowing($request, $result);

            return $this->successResponse($result, "Top users by $type", 200);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
