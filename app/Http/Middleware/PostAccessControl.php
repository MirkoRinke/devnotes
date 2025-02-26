<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Post;

class PostAccessControl {

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
public function handle(Request $request, Closure $next) {
        $user = $request->user();
        
        // Check if the user is authenticated before proceeding
        if (!$user) {
            return response()->json(['error' => 'Unauthorized. Authentication required.'], 401);
        }
        
        // Allow users with the specified role to access the resource
        if ($user->role === 'admin') {
            return $next($request);
        }
        
        // Allow users to access their own resources only
        if ($request->isMethod('put') || $request->isMethod('patch') || $request->isMethod('delete')) {
            $postId = $request->route('post');
            $post = Post::findOrFail($postId);
            
            if ($post->user_id === $user->id) {
                return $next($request);
            }
        }  

        
        return response()->json(['error' => 'Unauthorized. You do not have permission to perform this action.'], 403);
    }
}
