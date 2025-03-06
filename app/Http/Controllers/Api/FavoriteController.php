<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserFavorite;
use App\Models\Post;

use Exception; // Import the Exception class
use Illuminate\Database\Eloquent\ModelNotFoundException; // Import the ModelNotFoundException class


use App\Traits\ApiResponses; // Import the ApiResponses trait to use it in the controller example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\ApiSorting;  // Import the ApiSorting trait to use it in the controller example $query = $this->sort(request(), $query, ['id', 'title', 'language', 'category', 'status']);
use App\Traits\ApiFiltering; // Import the ApiFiltering trait to use it in the controller example $query = $this->filter(request(), $query, ['title', 'language', 'category', 'status']);
use App\Traits\SelectableAttributes; // Import the SelectableAttributes trait to use it in the controller example $this->selectAttributes($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiPagination; // Import the ApiPagination trait to use it in the controller example $this->getPerPage($request, $query, 10);
use App\Traits\QueryBuilder; // Import the QueryBuilder trait to use it in the controller example $this->buildQuery($request, $query, $methods);


use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class FavoriteController extends Controller {

    // Use the ApiResponses, ApiSorting, ApiFiltering , SelectableAttributes , ApiPagination and QueryBuilder traits
    use AuthorizesRequests , ApiResponses, ApiSorting, ApiFiltering , SelectableAttributes , ApiPagination , QueryBuilder;   


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

        $query = UserFavorite::where('user_id', $user->id)->with('post');

        $methods = [
            'sort' => ['id', 'user_id', 'title', 'language', 'category', 'tags', 'status'],
            'filter' => ['title', 'user_id', 'language', 'category', 'tags', 'status'],
            'select' => ['id', 'user_id', 'title', 'code' , 'description', 'resources', 'language', 'category', 'tags', 'status'],
            'getPerPage' => 10
        ];

        $query = $this->buildQuery($request, $query, $methods);

        // Check if the query is an instance of JsonResponse and return the response
        if ($query instanceof JsonResponse) {
            return $query;
        }

        if ($query->isEmpty()) {
            return $this->successResponse($query, 'No favorites found', 200);
        }

        // Decode the JSON data from the database to an array
        $query = $this->jsonDecode($query);

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
                $favorite = UserFavorite::create(['user_id' => $user->id,'post_id' => $post->id]);
                
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
