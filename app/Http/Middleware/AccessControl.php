<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AccessControl{
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
        if ($user->role === 'user') {
            $requestedUserId = $request->route('user');
            if ($requestedUserId && $requestedUserId == $user->id) {
                return $next($request);
            }
        }
        
        return response()->json(['error' => 'Unauthorized. Admin access required.'], 403);
    }
    
}