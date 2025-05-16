<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Traits\ApiResponses;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates API keys for all protected routes
 *
 * This middleware ensures that all requests include a valid API key,
 * either in the X-API-Key header or as an api_key query parameter.
 * It also tracks usage by updating the last_used_at timestamp.
 */
class ValidateApiKey {

    /**
     *  The traits used in the class
     */
    use ApiResponses;

    /**
     * Handle an incoming request.
     *
     * @param Request $request The incoming HTTP request
     * @param Closure $next The next middleware in the pipeline
     * @return Response Either the next middleware response or an error response
     *
     * @example | $middleware->alias([
     *              'api-key' => ValidateApiKey::class,
     *            ]);
     * 
     * Possible error responses:
     * 
     * @response status=401 scenario="API Key Missing" {
     *   "status": "error",
     *   "message": "This request requires a valid API key in the X-API-Key header or api_key query parameter",
     *   "code": 401,
     *   "errors": "API_KEY_MISSING"
     * }
     * 
     * @response status=401 scenario="Invalid API Key" {
     *   "status": "error",
     *   "message": "The provided API key is invalid or has been deactivated",
     *   "code": 401,
     *   "errors": "INVALID_API_KEY"
     * }
     */
    public function handle(Request $request, Closure $next): Response {

        // Get the API key from the request header or query parameter
        $apiKey = $request->header('X-API-Key') ?: $request->query('api_key');

        if (!$apiKey) {
            return $this->errorResponse('This request requires a valid API key in the X-API-Key header or api_key query parameter', 'API_KEY_MISSING', 401);
        }

        // Check if the API key is valid and active
        $validKey = ApiKey::where('key', $apiKey)
            ->where('active', true)
            ->first();

        if (!$validKey) {
            return $this->errorResponse('The provided API key is invalid or has been deactivated', 'INVALID_API_KEY', 401);
        }

        // Update the last_used_at timestamp of the API key
        $validKey->update(['last_used_at' => now()]);


        // Continue with the request
        return $next($request);
    }
}
