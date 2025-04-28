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

use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller {

    /**
     *  The traits used in the controller
     */
    use  ApiResponses, QueryBuilder, RelationLoader, AuthorizesRequests;


    /**
     * Update the favorite_count for a post
     */
    private function updateFavoriteCount($favorite, $method = 'increment') {
        $favorite->$method('favorite_count');
    }

    /**
     * Get all favorites for the authenticated user
     */
    public function index(Request $request): JsonResponse {
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
    }

    /**
     * Add a post to favorites
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
     * Remove a post from favorites
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

            $query = $this->loadRelation($request, $query, 'user', 'user_id', ['id', 'display_name']);

            $query = $this->buildQuery($request, $query, 'post');

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
