<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use App\Models\UserFavorite;
use App\Models\Post;

use App\Traits\ApiResponses; // example $this->successResponse($favorites, 'Favorites retrieved successfully', 200);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);
use App\Traits\RelationLoader; // examples:
// - Single relation: $this->loadRelation($request, $query, 'user', 'user_id', ['id', 'display_name'])
// - Multiple relations: $this->loadRelations($request, $query, [
//     ['relation' => 'user', 'foreignKey' => 'user_id', 'columns' => ['id', 'display_name']],
//     ['relation' => 'post', 'foreignKey' => 'post_id', 'columns' => ['id', 'title']]
// ])
use App\Traits\PostFieldManager; // example $this->manageFieldVisibility($request, $query);

use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller {

    /**
     *  The traits used in the controller
     */
    use  ApiResponses, QueryBuilder, RelationLoader, AuthorizesRequests, PostFieldManager;


    /**
     * Update the favorite_count for a post
     */
    private function updateFavoriteCount($favorite, $method = 'increment') {
        $favorite->$method('favorite_count');
    }

    /**
     * Get All User Favorites
     * 
     * Endpoint: GET /user/favorites
     *
     * Retrieves all favorites for the authenticated user.
     * Shows the relationship objects between users and posts, not the post content.
     * 
     * @group User Favorites
     *
     * @queryParam select string Select specific fields (id,user_id,post_id,etc). Example: id,post_id,created_at
     * @queryParam sort string Field to sort by (prefix with - for DESC order). Example: -created_at
     * @queryParam filter[field] string Filter by specific fields. Example: filter[post_id]=5
     * 
     * @queryParam page integer Page number for pagination. Example: 1
     * @queryParam per_page integer Number of items per page. Example: 15 (Default: 10)
     * 
     * Example URL: /user/favorites
     * 
     * @response status=200 scenario="Favorites retrieved" {
     *   "status": "success",
     *   "message": "Favorites retrieved successfully",
     *   "code": 200,
     *   "count": 2,
     *   "data": [
     *     {
     *       "id": 5,
     *       "user_id": 2,
     *       "post_id": 12,
     *       "created_at": "2025-04-10T16:32:18.000000Z",
     *       "updated_at": "2025-04-10T16:32:18.000000Z"
     *     },
     *     {
     *       "id": 8,
     *       "user_id": 2,
     *       "post_id": 7,
     *       "created_at": "2025-04-15T09:22:41.000000Z",
     *       "updated_at": "2025-04-15T09:22:41.000000Z"
     *     }
     *   ]
     * }
     *
     * @response status=200 scenario="No favorites found" {
     *   "status": "success",
     *   "message": "No favorites found",
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
     * @authenticated
     */
    public function index(Request $request): JsonResponse {
        try {

            $user = $request->user();

            $query = UserFavorite::where('user_id', $user->id);

            $query = $this->buildQuery($request, $query, 'user_favorites');

            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No favorites found', 200);
            }

            return $this->successResponse($query, 'Favorites retrieved successfully', 200);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Add a Post to Favorites
     * 
     * Endpoint: POST /posts/{postId}/favorites
     *
     * Adds the specified post to the authenticated user's favorites list.
     * If the post is already in the user's favorites, the existing favorite relationship is returned.
     * 
     * This operation also increments the favorite_count of the post.
     * 
     * @group User Favorites
     *
     * @urlParam postId integer required The ID of the post to add to favorites. Example: 12
     * 
     * Example URL: /posts/12/favorites
     * 
     * @response status=201 scenario="Post added to favorites" {
     *   "status": "success",
     *   "message": "Post successfully added to favorites",
     *   "code": 201,
     *   "count": 1,
     *   "data": {
     *     "id": 15,
     *     "user_id": 2,
     *     "post_id": 12,
     *     "created_at": "2025-05-04T19:32:18.000000Z",
     *     "updated_at": "2025-05-04T19:32:18.000000Z"
     *   }
     * }
     * 
     * @response status=200 scenario="Post already in favorites" {
     *   "status": "success",
     *   "message": "Post already in favorites",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 8,
     *     "user_id": 2,
     *     "post_id": 12,
     *     "created_at": "2025-04-15T09:22:41.000000Z",
     *     "updated_at": "2025-04-15T09:22:41.000000Z"
     *   }
     * }
     *
     * @response status=404 scenario="Post not found" {
     *   "status": "error",
     *   "message": "Post not found",
     *   "code": 404,
     *   "errors": "POST_NOT_FOUND"
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
    public function store(Request $request, $postId): JsonResponse {
        try {
            $user = $request->user();
            $post = Post::findOrFail($postId);

            $exists = UserFavorite::where('user_id', $user->id)->where('post_id', $post->id)->exists();

            if (!$exists) {
                $favorite = DB::transaction(function () use ($user, $post) {
                    $favorite = UserFavorite::create([
                        'user_id' => $user->id,
                        'post_id' => $post->id
                    ]);

                    $this->updateFavoriteCount($post, 'increment');

                    return $favorite;
                });
                return $this->successResponse($favorite, 'Post successfully added to favorites', 201);
            } else {
                $favorite = UserFavorite::where('user_id', $user->id)->where('post_id', $post->id)->first();

                return $this->successResponse($favorite, 'Post already in favorites', 200);
            }
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Post not found', 'POST_NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Remove a Post from Favorites
     * 
     * Endpoint: DELETE /posts/{postId}/favorites
     *
     * Removes the specified post from the authenticated user's favorites list.
     * 
     * This operation also decrements the favorite_count of the post.
     * 
     * @group User Favorites
     *
     * @urlParam postId integer required The ID of the post to remove from favorites. Example: 12
     * 
     * Example URL: /posts/12/favorites
     * 
     * @response status=200 scenario="Post removed from favorites" {
     *   "status": "success",
     *   "message": "Post successfully removed from favorites",
     *   "code": 200,
     *   "count": 0,
     *   "data": null
     * }
     * 
     * @response status=404 scenario="Post not found" {
     *   "status": "error",
     *   "message": "Post not found",
     *   "code": 404,
     *   "errors": "POST_NOT_FOUND"
     * }
     *
     * @response status=404 scenario="Post not in favorites" {
     *   "status": "error",
     *   "message": "Post is not in favorites",
     *   "code": 404,
     *   "errors": "POST_NOT_IN_FAVORITES"
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
    public function destroy(Request $request, $postId): JsonResponse {
        try {
            $user = $request->user();
            $post = Post::findOrFail($postId);

            $favorite = UserFavorite::where('user_id', $user->id)->where('post_id', $postId)->first();

            if (!$favorite) {
                return $this->errorResponse('Post is not in favorites', 'POST_NOT_IN_FAVORITES', 404);
            }

            DB::transaction(function () use ($post, $favorite) {
                $this->updateFavoriteCount($post, 'decrement');
                $favorite->delete();
            });

            return $this->successResponse(null, 'Post successfully removed from favorites', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Post not found', 'POST_NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Get User's Favorited Posts
     * 
     * Endpoint: GET user/favorites/posts
     *
     * Retrieves a list of posts that have been favorited by the authenticated user.
     * Returns the full post objects rather than just the favorite relationship.
     * Supports the same query parameters as the post index endpoint for consistent filtering, sorting, and pagination.
     * 
     * Note: Access controls are automatically applied based on the authenticated user's role:
     * - Regular users will only see posts they created themselves (regardless of status) OR 
     *   published posts by others with fewer than 5 reports
     * - Admin/moderator users can see all favorited posts regardless of status
     * - Certain fields like 'moderation_info' are only visible to users with admin or moderator roles
     * 
     * This means a post that was favorited might not appear in results if its status changed 
     * from "published" to another status (like "draft" or "archived").
     * 
     * @group User Favorites
     *
     * @queryParam select string Select specific fields from posts (id,title,language,etc). Example: id,title,language
     * @queryParam sort string Field to sort by (prefix with - for DESC order). Example: -created_at
     * @queryParam filter[field] string Filter by specific fields. Example: filter[language]=php
     * @queryParam page integer Page number for pagination. Example: 1
     * @queryParam per_page integer Number of items per page. Example: 15 (Default: 10)
     * 
     * Example URL (basic): user/favorites/posts
     * 
     * @response status=200 scenario="Complete favorited post data" {
     *   "status": "success",
     *   "message": "Favorited posts retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 7,
     *       "created_at": "2025-05-01T14:24:38.000000Z",
     *       "updated_at": "2025-05-01T16:03:51.000000Z",
     *       "user_id": 1,
     *       "title": "Git: Branching",
     *       "code": "git checkout -b feature-branch",
     *       "description": "Learn how to create and manage branches with Git.",
     *       "images": [
     *         "https://picsum.photos/200",
     *         "https://picsum.photos/200"
     *       ],
     *       "videos": [
     *         "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
     *         "https://www.youtube.com/watch?v=dQw4w9WgXcQ"
     *       ],
     *       "resources": [
     *         "https://git-scm.com/book/en/v2/Git-Branching-Branches-in-a-Nutshell"
     *       ],
     *       "external_source_previews": [
     *         {
     *           "url": "https://picsum.photos/200",
     *           "type": "images",
     *           "domain": "picsum.photos"
     *         },
     *         {
     *           "url": "https://picsum.photos/200",
     *           "type": "images",
     *           "domain": "picsum.photos"
     *         },
     *         {
     *           "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
     *           "type": "videos",
     *           "domain": "www.youtube.com"
     *         },
     *         {
     *           "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
     *           "type": "videos",
     *           "domain": "www.youtube.com"
     *         },
     *         {
     *           "url": "https://git-scm.com/book/en/v2/Git-Branching-Branches-in-a-Nutshell",
     *           "type": "resources",
     *           "domain": "git-scm.com"
     *         }
     *       ],
     *       "language": [
     *         "Shell"
     *       ],
     *       "category": "DevOps",
     *       "post_type": "tutorial",
     *       "technology": null,
     *       "tags": [
     *         "git",
     *         "branching"
     *       ],
     *       "status": "published",
     *       "favorite_count": 1,
     *       "likes_count": 2,
     *       "reports_count": 0,
     *       "comments_count": 0,
     *       "is_updated": false,
     *       "updated_by_role": null,
     *       "last_comment_at": "2025-05-01T16:03:51.000000Z",
     *       "history": null,
     *       "moderation_info": null,       ||  // Only visible to admin and moderator
     *       "user": {
     *         "id": 1,
     *         "display_name": "Admin User"
     *       }
     *     }
     *   ]
     * }
     * 
     * Example URL (optimized for list view): user/favorites/posts?select=id,title,language
     * 
     * @response status=200 scenario="Selected fields with select parameter" {
     *   "status": "success",
     *   "message": "Favorited posts retrieved successfully",
     *   "code": 200,
     *   "count": 2,
     *   "data": [
     *     {
     *       "id": 7,
     *       "title": "Git: Branching",
     *       "language": ["Shell"]
     *     },
     *     {
     *       "id": 12,
     *       "title": "JavaScript Array Methods Cheatsheet",
     *       "language": ["JavaScript"]
     *     }
     *   ]
     * }
     *
     * @response status=200 scenario="No favorited posts found" {
     *   "status": "success",
     *   "message": "No favorited posts found",
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
     * @authenticated
     */
    public function getFavoritePosts(Request $request): JsonResponse {
        try {
            $userId = $request->user()->id;

            // Get posts that have been favorited by this user
            $query = Post::query()->whereHas('favorites', function ($subQuery) use ($userId) {
                $subQuery->where('user_id', $userId);
            });

            $query = $this->loadRelation($request, $query, 'user', 'user_id', ['id', 'display_name']);

            $query = $this->applyAccessFilters($request, $query);

            $query = $this->buildQuery($request, $query, 'post');
            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse([], 'No favorited posts found', 200);
            }

            $query = $this->manageFieldVisibility($request, $query);

            return $this->successResponse($query, 'Favorited posts retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
