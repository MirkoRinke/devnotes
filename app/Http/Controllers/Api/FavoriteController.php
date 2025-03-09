<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserFavorite;
use App\Models\Post;

use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;


use App\Traits\ApiResponses; // example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\ApiSorting;  // example $query = $this->sort(request(), $query, ['id', 'title', 'language', 'category', 'status']);
use App\Traits\ApiFiltering; // example $query = $this->filter(request(), $query, ['title', 'language', 'category', 'status']);
use App\Traits\SelectableAttributes; // example $this->selectAttributes($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiPagination; // example $this->getPerPage($request, $query, 10);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);


use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class FavoriteController extends Controller {

    /**
     *  The traits used in the controller
     */
    use AuthorizesRequests, ApiResponses, ApiSorting, ApiFiltering, SelectableAttributes, ApiPagination, QueryBuilder;

    /**
     * The methods array contains the methods that are used in the buildQuery method
     */
    private $methods = [
        'sort' => ['id', 'user_id', 'title', 'language', 'category', 'tags', 'status', 'created_at', 'updated_at'],
        'filter' => ['title', 'user_id', 'language', 'category', 'tags', 'status', 'created_at', 'updated_at'],
        'select' => ['id', 'user_id', 'title', 'code', 'description', 'resources', 'language', 'category', 'tags', 'status', 'created_at', 'updated_at'],
        'getPerPage' => 10
    ];

    /**
     * Get all favorites
     */
    public function getFavorites(Request $request) {
        $user = $request->user();

        $query = UserFavorite::where('user_id', $user->id)->with('post');

        $query = $this->buildQuery($request, $query, $this->methods);

        // Check if the query is an instance of JsonResponse and return the response
        if ($query instanceof JsonResponse) {
            return $query;
        }

        // Check if the query is empty and return a response
        if ($query->isEmpty()) {
            return $this->successResponse($query, 'No favorites found', 200);
        }

        return $this->successResponse($query, 'Favorites retrieved successfully', 200);
    }

    /**
     * Add a post to favorites
     */
    public function addFavorite(Request $request, $postId) {
        try {
            $user = $request->user();
            $post = Post::findOrFail($postId);

            // Check if the post is already in favorites
            $exists = UserFavorite::where('user_id', $user->id)->where('post_id', $post->id)->exists();

            if (!$exists) {
                // Add the post to favorites
                $favorite = UserFavorite::create(['user_id' => $user->id, 'post_id' => $post->id]);

                // Increment the favorite count for the post
                $post->increment('favorite_count');

                return $this->successResponse($favorite, 'Post successfully added to favorites', 201);
            } else {
                // Return the favorite if the post is already in favorites
                $favorite = UserFavorite::where('user_id', $user->id)->where('post_id', $post->id)->first();

                return $this->successResponse($favorite, 'Post already in favorites', 200);
            }
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Post not found', 'POST_NOT_FOUND', 404);
        }
    }

    /**
     * Remove a post from favorites
     */
    public function removeFavorite(Request $request, $postId) {
        try {
            $user = $request->user();
            $post = Post::findOrFail($postId);

            $favorite = UserFavorite::where('user_id', $user->id)->where('post_id', $postId)->first();

            if (!$favorite) {
                return $this->errorResponse('Post is not in favorites', 'POST_NOT_IN_FAVORITES', 404);
            }

            $this->authorize('delete', $favorite);

            // Decrement the favorite count for the post and then delete the favorite
            $post->decrement('favorite_count');

            $favorite->delete();
            return $this->successResponse(null, 'Post successfully removed from favorites', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Post not found', 'POST_NOT_FOUND', 404);
        }
    }
}
