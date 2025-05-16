<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use app\Traits\ApiResponses;

/**
 * Middleware to ensure the user's email is verified
 * 
 * This middleware checks if the authenticated user has a verified email address.
 * If the email is not verified, it returns a 403 Forbidden response.
 */
class EnsureEmailIsVerifiedApi {

    /**
     *  The traits used in the controller
     */
    use ApiResponses;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     * 
     * @example | $middleware->alias([
     *              'email-verified' => EnsureEmailIsVerifiedApi::class,
     *            ]); 
     * 
     * \bootstrap\app.php
     * 
     * Possible error responses:
     * 
     * @response status=403 scenario="Email Not Verified" {
     *   "status": "error",
     *   "message": "Your email address is not verified.",
     *   "code": 403,
     *   "errors": "EMAIL_NOT_VERIFIED"
     * }
     * 
     */
    public function handle(Request $request, Closure $next): Response {
        if (!$request->user() || !$request->user()->hasVerifiedEmail()) {
            return $this->errorResponse('Your email address is not verified.', 'EMAIL_NOT_VERIFIED', 403);
        }
        return $next($request);
    }
}
