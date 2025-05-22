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
     * 
     * Example URL: /users/1/post-interactions/?type=likes_count
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Total likes_count for user with ID 1",
     *   "code": 200,
     *   "count": 1,
     *   "data": 3
     * }
     * 
     * Example URL: /users/1/post-interactions/?type=favorite_count
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Total favorite_count for user with ID 1",
     *   "code": 200,
     *   "count": 1,
     *   "data": 4
     * }
     * 
     * Example URL: /users/1/post-interactions/?type=interactions
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Total interactions for user with ID 1",
     *   "code": 200,
     *   "count": 1,
     *   "data": 7
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
     * Note: This endpoint sums the total number of likes, favorites, or both (interactions)
     * received on posts created by the specified user. It doesn't count interactions on individual comments.
     * 
     * @authenticated
     */
    public function getUserPostsInteractions(Request $request, string $id) {
        try {
            $hasPosts = Post::where('user_id', $id)->firstOrFail();

            $validatedData = $request->validate(
                [
                    'type' => 'required|string|in:likes_count,favorite_count,interactions',
                ],
                $this->getValidationMessages('Post')
            );

            if ($validatedData['type'] === 'likes_count' || $validatedData['type'] === 'favorite_count') {
                $total = (int)Post::where('user_id', $id)->sum($validatedData['type']);
            }

            if ($validatedData['type'] === 'interactions') {
                $total = (int)Post::where('user_id', $id)->sum('likes_count') + Post::where('user_id', $id)->sum('favorite_count');
            }

            return $this->successResponse($total, 'Total ' . $validatedData['type'] . ' for user with ID ' . $id, 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("No posts found for user with ID $id", 'NO_POSTS_FOUND', 404);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
