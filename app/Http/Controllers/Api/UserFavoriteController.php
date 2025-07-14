<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use App\Models\UserFavorite;
use App\Models\Post;

use App\Traits\ApiResponses;
use App\Traits\QueryBuilder;
use App\Traits\ApiInclude;
use App\Traits\RelationLoader;
use App\Traits\FieldManager;
use App\Traits\PostQuerySetup;
use App\Traits\FavoriteHelper;
use App\Traits\UserLikeHelper;
use App\Traits\UserFollowerHelper;


use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;

/**
 * Controller handling user favorites functionality for posts.
 * 
 * This controller provides endpoints to manage user favorite posts:
 * - Retrieving a user's favorite relationships
 * - Adding/removing posts from favorites
 * - Fetching the complete post objects that a user has favorited
 * 
 * All operations maintain favorite counts consistency on the post objects.
 */
class UserFavoriteController extends Controller {

    /**
     *  The traits used in the controller
     */
    use  ApiResponses, QueryBuilder, ApiInclude, RelationLoader, AuthorizesRequests, FieldManager, PostQuerySetup, FavoriteHelper, UserLikeHelper, UserFollowerHelper;


    /**
     * Update the favorite_count for a post
     * 
     * @param Post $post The post model instance
     * @param string $method The method to use ('increment' or 'decrement')
     * @return void
     * 
     * @example | $this->updateFavoriteCount($post, 'increment');
     */
    private function updateFavoriteCount($post, $method) {
        $post->$method('favorite_count');
    }


    /**
     * Get All User Favorites
     * 
     * Endpoint: GET /user/favorites
     *
     * Returns a paginated list of favorite relationships for the authenticated user.
     * Only the user's own favorites are returned.
     * 
     * @group Favorites
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
     * @queryParam page     Pagination, see [ApiPagination](#apipagination).
     * @see \App\Traits\ApiPagination::paginate()
     * 
     * @queryParam per_page Pagination, see [ApiPagination](#apipagination).
     * @see \App\Traits\ApiPagination::paginate()
     * 
     * @queryParam setLimit Disables pagination and limits the number of results. See [ApiLimit](#apilimit).
     * @see \App\Traits\ApiLimit::setLimit()
     *
     * Example URL: /user/favorites
     * 
     * @response status=200 scenario="Favorites retrieved" {
     *   "status": "success",
     *   "message": "Favorites retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 1237,
     *       "user_id": 1,
     *       "post_id": 27,
     *       "created_at": "2025-07-09T13:55:21.000000Z",
     *       "updated_at": "2025-07-09T13:55:21.000000Z"
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
     * If the post is already in the user's favorites, an info response is returned.
     * This operation also increments the favorite_count of the post.
     *
     * @group Favorites
     *
     * @urlParam postId integer required The ID of the post to add to favorites. Example: 28
     * 
     * @response status=201 scenario="Post added to favorites" {
     *   "status": "success",
     *   "message": "Post successfully added to favorites",
     *   "code": 201,
     *   "count": 1,
     *   "data": {
     *     "id": 1239,
     *     "user_id": 1,
     *     "post_id": 28,
     *     "created_at": "2025-07-09T14:25:41.000000Z",
     *     "updated_at": "2025-07-09T14:25:41.000000Z"
     *   }
     * }
     * 
     * @response status=200 scenario="Post already in favorites" {
     *   "status": "error",
     *   "message": "Post already in favorites",
     *   "code": 200,
     *   "errors": "POST_ALREADY_IN_FAVORITES"
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

                    // Create the favorite relationship
                    $favorite = new UserFavorite();

                    $favorite->user_id = $user->id;
                    $favorite->post_id = $post->id;

                    $favorite->save();

                    $this->updateFavoriteCount($post, 'increment');

                    return $favorite;
                });
                return $this->successResponse($favorite, 'Post successfully added to favorites', 201);
            } else {
                return $this->errorResponse('Post already in favorites', 'POST_ALREADY_IN_FAVORITES', 200);
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
     * Only the user's own favorites can be removed.
     * This operation also decrements the favorite_count of the post.
     * 
     * @group Favorites
     *
     * @urlParam postId integer required The ID of the post to remove from favorites. Example: 27
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
     * List All Favorited Posts for the Authenticated User
     * 
     * Endpoint: GET /user/favorites/posts
     *
     * Retrieves a list of posts that have been favorited by the authenticated user.
     * **By default, results are paginated.** 
     * 
     * The response structure and query parameters are identical to the main post index endpoint.
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
     * The relations `tags`, `languages`, and `technologies` are always included in the response and do not require the `include` parameter.
     * Other relations (e.g. `user`) can be included using the `include` parameter.
     *
     * You can use the `*_fields` parameter for all relations (e.g. `user_fields`, `tags_fields`, `languages_fields`, `technologies_fields`)
     * to specify which fields should be returned for each relation.
     * 
     * Example: `/user/favorites/posts?include=user&user_fields=id,display_name&tags_fields=name`
     *
     * @group Favorites
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
     * @queryParam include  See [ApiInclude](#apiinclude) for relation inclusion details (e.g. user). 
     * @see \App\Traits\ApiInclude::getRelationKeyFields()
     * 
     * @queryParam *_fields string See [ApiInclude](#apiinclude). When including a relation or for always-included relations (tags, languages, technologies), specify fields to return. Example: tags_fields=name
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest() for dynamic includes
     * @see \App\Traits\PostQuerySetup::getSelectRelationFields() for always-included relations
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
     * Example URL: /user/favorites/posts
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Favorited posts retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 42,
     *       "title": "Example Post Title",
     *       "code": "...",
     *       "description": "...",
     *       "images": [],                                  || Empty by default - requires user consent or owner access
     *       "videos": [                                    || Empty by default - requires user consent or owner access
     *         "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
     *         "https://www.youtube.com/watch?v=dQw4w9WgXcQ"
     *       ],                                
     *       "resources": [],                               || Empty by default - requires user consent or owner access
     *       "external_source_previews": [
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
     *           "url": "https://svelte.dev/docs#run-time-store",
     *           "type": "resources",
     *           "domain": "svelte.dev"
     *         }
     *       ],
     *       "category": "Machine Learning",                || See /post-allowed-values/?filter[type]=category for valid values.
     *       "post_type": "Feedback",                       || See /post-allowed-values/?filter[type]=post_type for valid values.
     *       "status": "Published",                         || See /post-allowed-values/?filter[type]=status for valid values. 
     *       "favorite_count": 1,
     *       "likes_count": 0,
     *       "reports_count": 0,                            || Admin and Moderator only
     *       "comments_count": 0,
     *       "is_updated": false,
     *       "updated_by_role": null,
     *       "last_comment_at": null,
     *       "history": [],
     *       "moderation_info": [],                         || Admin and Moderator only
     *       "created_at": "2025-06-23T22:52:38.000000Z",
     *       "updated_at": "2025-06-23T22:53:53.000000Z",
     *       "is_favorited": false,                         || Virtual field, true if the authenticated user has favorited this post
     *       "is_liked": false,                             || Virtual field, true if the authenticated user has liked this post
     *       "tags": [                                      || See /post-allowed-values/?filter[type]=tag for valid values.
     *         { "id": 1, "name": "Laravel" },              || Note: Users can create new tags when posting; other allowed values are admin-only.
     *         { "id": 2, "name": "PHP" },
     *         { "id": 3, "name": "Backend" }
     *       ],
     *       "languages": [                                 || See /post-allowed-values/?filter[type]=language for valid values.
     *         { "id": 4, "name": "Java" },
     *         { "id": 5, "name": "C#" },
     *         { "id": 6, "name": "TypeScript" }
     *       ],
     *       "technologies": [                              || See /post-allowed-values/?filter[type]=technology for valid values.
     *         { "id": 7, "name": "Bootstrap" },
     *         { "id": 8, "name": "TailwindCSS" },
     *         { "id": 9, "name": "Material UI" }
     *       ]
     *     }
     *   ]
     * }
     * 
     * Example URL: /user/favorites/posts/?include=user
     *
     * @response status=200 scenario="Success with user include" {
     *   "status": "success",
     *   "message": "Favorited posts retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *      ..... || Same favorite post data as above
     *       "user": {
     *          "id": 42,
     *          "display_name": "John Doe",
     *          "role": "user",
     *          "created_at": "2025-06-23T22:52:35.000000Z",
     *          "updated_at": "2025-06-23T22:52:35.000000Z",
     *          "is_banned": null,                      || Admin and Moderator only
     *          "was_ever_banned": false,               || Admin and Moderator only
     *          "moderation_info": [],                  || Admin and Moderator only
     *          "is_following": false                   || Virtual field, true if the authenticated user follows this user
     *        },
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
     * Note: External content (images, videos, resources) is not displayed by default for privacy reasons.
     * To view this content, one of the following conditions must be met:
     * 1. You are the owner of the post (automatically shows all content)
     * 2. For non-authenticated users: Send header X-Show-External-Images: true (similarly for videos/resources)
     * 3. For authenticated users: Either have auto_load_external_images set to true in user profile,
     *    or have a valid temporary permission (external_images_temp_until date is in the future)
     *
     * @authenticated
     */
    public function getFavoritePosts(Request $request): JsonResponse {
        try {
            $user = $request->user();
            // $userId = $user->id;

            // Get posts that have been favorited by this user
            $query = Post::query()->whereHas('favorites', function ($subQuery) use ($user) {
                $subQuery->where('user_id', $user->id);
            });

            $originalSelectFields = $this->getSelectFields($request);

            $posts = $this->setupPostQuery($request, $query, 'buildQuery');
            if ($posts instanceof JsonResponse) {
                return $posts;
            }

            if ($posts->isEmpty()) {
                return $this->successResponse($posts, 'No favorited posts found', 200);
            }

            $posts = $this->managePostsFieldVisibility($request, $posts);

            $posts = $this->checkForIncludedRelations($request, $posts);

            $posts = $this->controlVisibleFields($request, $originalSelectFields, $posts);

            $posts = $this->isFavorited($request, $user, $posts, $originalSelectFields);

            $posts = $this->isLiked($request, $user, $posts, 'post', $originalSelectFields);

            $posts = $this->isFollowing($request, $posts);

            return $this->successResponse($posts, 'Favorited posts retrieved successfully', 200);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
