<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use App\Traits\ApiResponses; // Import the ApiResponses trait to use it in the controller example $this->successResponse($users);

class AccessControl{

    use ApiResponses; // Use the ApiResponses trait in the controller

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next) {
        $user = $request->user();
        
        // Check if the user is authenticated before proceeding
        if (!$user) {
            return $this->errorResponse('Unauthorized. Authentication required.', [], 401);
        }
        
        // Allow users with the specified role to access the resource
        if ($user->role === 'admin') {
            return $next($request);
        }
        
        // Allow users to access their own resources only
        if ($user->role === 'user') {
            $requestedUserId = $request->route('user');
            if ($requestedUserId && $requestedUserId == $user->id) {
                return $next($request);
            }
        }
        
        return $this->errorResponse('Unauthorized. Admin access required.', [], 403);
    }
    
}