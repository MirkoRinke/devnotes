<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserFavorite;
use App\Models\Post;

use Exception; // Import the Exception class
use Illuminate\Database\Eloquent\ModelNotFoundException; // Import the ModelNotFoundException class


use App\Traits\ApiResponses; // Import the ApiResponses trait to use it in the controller example return $this->successResponse($posts, 'Posts retrieved successfully', 200);


use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class FavoriteController extends Controller {

    // Use the AuthorizesRequests and ApiResponses traits in the FavoriteController
    use AuthorizesRequests , ApiResponses;


    // Decode the JSON data from the database to an array
    private function jsonDecode($favorites) {
        // Entfernen Sie dd($favorites); 
        foreach ($favorites as $favorite) {
            $post = $favorite->post;
            if (isset($post->tags)) {
                $post->tags = json_decode($post->tags);
            }
            if (isset($post->resources)) {
                $post->resources = json_decode($post->resources);
            }
        }        
        return $favorites;
    }

    /**
     * Get all favorites
     */
    public function getFavorites(Request $request) {
        $user = $request->user();
        $favorites = $user->favorites()->with('post')->get();

        if ($favorites->isEmpty()) {
            return $this->successResponse($favorites, 'No favorites found', 200);
        }

        // Decode the JSON data from the database to an array
        $favorites = $this->jsonDecode($favorites);

        return $this->successResponse($favorites, 'Favorites retrieved successfully', 200);
    }

    /**
     * Add a post to favorites
     */
    public function addFavorite(Request $request, $postId) {
        try {
            $user = $request->user();
            $post = Post::findOrFail($postId);
            
            $favorite = UserFavorite::firstOrCreate([
                'user_id' => $user->id,
                'post_id' => $post->id
            ]);          
            
            return $this->successResponse($favorite, 'Post successfully added to favorites', 201);
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
            
            $favorite = UserFavorite::where('user_id', $user->id)
                                    ->where('post_id', $postId)
                                    ->first();

            if (!$favorite) {
                return $this->errorResponse('Post is not in favorites', 'POST_NOT_IN_FAVORITES', 404);
            }
    
            $this->authorize('delete', $favorite);
    
            $favorite->delete();
            return $this->successResponse(null, 'Post successfully removed from favorites', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Post not found', 'POST_NOT_FOUND', 404);
        }
    }
        
}
