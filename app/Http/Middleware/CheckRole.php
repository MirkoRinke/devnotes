<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole{
    public function handle(Request $request, Closure $next, $role) {    
        if (!$request->user() || $request->user()->role !== $role) {
            return response()->json(['error' => 'Unauthorized. Required role: ' . $role], 403);
        }    
        return $next($request);
    } 
}