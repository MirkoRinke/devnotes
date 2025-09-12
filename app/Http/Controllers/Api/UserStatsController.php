<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Post;

use App\Traits\ApiResponses;

use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class UserStatsController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses;


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
     *   "data": 7
     * }
     * 
     * Example URL: /users/1/post-interactions/?type=favorite_count
     *
     * @response status=200 scenario="Success (favorite_count)" {
     *   "status": "success",
     *   "message": "Total favorite_count for user with ID 1",
     *   "code": 200,
     *   "count": 1,
     *   "data": 5
     * }
     * 
     * Example URL: /users/1/post-interactions/?type=interactions
     * 
     * @response status=200 scenario="Success (interactions)" {
     *   "status": "success",
     *   "message": "Total interactions for user with ID 1",
     *   "code": 200,
     *   "count": 1,
     *   "data": 12
     * }
     * 
     * @response status=200 scenario="No posts found" {
     *   "status": "success",
     *   "message": "Total likes_count for user with ID 1",
     *   "code": 200,
     *   "count": 1,
     *   "data": 0
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

            $query = Post::where('user_id', $id);

            if ($period) {
                $query->where('created_at', '>=', now()->{$period}());
            }

            if ($validatedData['type'] === 'likes_count' || $validatedData['type'] === 'favorite_count') {
                $total = (int)$query->sum($validatedData['type']);
            }

            if ($validatedData['type'] === 'interactions') {
                $total = (int)$query->sum('likes_count') + $query->sum('favorite_count');
            }

            return $this->successResponse($total, 'Total ' . $validatedData['type'] . ' for user with ID ' . $id, 200);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
