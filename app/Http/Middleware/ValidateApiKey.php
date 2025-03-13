<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Traits\ApiResponses;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey {
    use ApiResponses;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response {

        // Get the API key from the request header
        $apiKey = $request->header('X-API-Key');


        // If the API key is missing, return an error response
        if (!$apiKey) {
            return $this->errorResponse('This request requires a valid API key in the X-API-Key header', 'API_KEY_MISSING', 401);
        }


        // Check if the API key is valid and active
        $validKey = ApiKey::where('key', $apiKey)
            ->where('active', true)
            ->first();


        // If the API key is invalid, return an error response
        if (!$validKey) {
            return $this->errorResponse('The provided API key is invalid or has been deactivated', 'INVALID_API_KEY', 401);
        }

        // Update the last_used_at timestamp of the API key
        $validKey->update(['last_used_at' => now()]);


        // Continue with the request
        return $next($request);
    }
}
