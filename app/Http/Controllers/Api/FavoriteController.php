<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\UserFavorite;
use App\Models\Post;

use App\Traits\ApiResponses; // example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\ApiSorting;  // example $query = $this->sort(request(), $query, ['id', 'title', 'language', 'category', 'status']);
use App\Traits\ApiFiltering; // example $query = $this->filter(request(), $query, ['title', 'language', 'category', 'status']);
use App\Traits\SelectableAttributes; // example $this->selectAttributes($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiPagination; // example $this->getPerPage($request, $query, 10);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class FavoriteController extends Controller {

    /**
     *  The traits used in the controller
     */
    use AuthorizesRequests, ApiResponses, ApiSorting, ApiFiltering, SelectableAttributes, ApiPagination, QueryBuilder;

    /**
     * The methods array contains the methods that are used in the buildQuery method
     */
    private $methodsFavorites = [
        'sort' =>  ['id', 'user_id', 'post_id', 'created_at', 'updated_at'],
        'filter' => ['id', 'user_id', 'post_id', 'created_at', 'updated_at'],
        'select' =>  ['id', 'user_id', 'post_id', 'created_at', 'updated_at'],
        'getPerPage' => 10
    ];

    private $methodsPosts = [
        'sort' => ['id', 'user_id', 'title', 'language', 'category', 'tags', 'status', 'favorite_count', 'created_at', 'updated_at'],
        'filter' => ['title', 'user_id', 'language', 'category', 'tags', 'status', 'created_at', 'updated_at'],
        'select' => ['id', 'user_id', 'title', 'code', 'description', 'resources', 'language', 'category', 'tags', 'status', 'favorite_count', 'reports_count', 'created_at', 'updated_at'],
        'getPerPage' => 10
    ];

    /**
     * Get all favorites
     */
    public function getFavorites(Request $request): JsonResponse {
        $user = $request->user();

        $query = UserFavorite::where('user_id', $user->id);

        /**
         *  Include the user and post entity in the response
         */
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            $allowedIncludes = ['post'];
            $validIncludes = array_intersect($includes, $allowedIncludes);

            if (!empty($validIncludes)) {
                $query->with($validIncludes);
            }
        }

        $query = $this->buildQuery($request, $query, $this->methodsFavorites);

        if ($query instanceof JsonResponse) {
            return $query;
        }

        if ($query->isEmpty()) {
            return $this->successResponse($query, 'No favorites found', 200);
        }

        return $this->successResponse($query, 'Favorites retrieved successfully', 200);
    }

    /**
     * Add a post to favorites
     */
    public function addFavorite(Request $request, $postId): JsonResponse {
        try {
            $user = $request->user();
            $post = Post::findOrFail($postId);

            $exists = UserFavorite::where('user_id', $user->id)->where('post_id', $post->id)->exists();

            if (!$exists) {
                $favorite = UserFavorite::create(['user_id' => $user->id, 'post_id' => $post->id]);

                $post->increment('favorite_count');

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
     * Remove a post from favorites
     */
    public function removeFavorite(Request $request, $postId): JsonResponse {
        try {
            $user = $request->user();
            $post = Post::findOrFail($postId);

            $favorite = UserFavorite::where('user_id', $user->id)->where('post_id', $postId)->first();

            if (!$favorite) {
                return $this->errorResponse('Post is not in favorites', 'POST_NOT_IN_FAVORITES', 404);
            }

            $this->authorize('delete', $favorite);

            $post->decrement('favorite_count');
            $favorite->delete();

            return $this->successResponse(null, 'Post successfully removed from favorites', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Post not found', 'POST_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Display a listing of posts that are favorited by the authenticated user.
     * Supports the same query parameters as the post index endpoint.
     */
    public function getFavoritePosts(Request $request): JsonResponse {
        try {
            $userId = $request->user()->id;

            // Get posts that have been favorited by this user
            $query = Post::query()->whereHas('favorites', function ($subQuery) use ($userId) {
                $subQuery->where('user_id', $userId);
            });

            $query = $this->buildQuery($request, $query, $this->methodsPosts);

            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse([], 'No favorited posts found', 200);
            }

            return $this->successResponse($query, 'Favorited posts retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
