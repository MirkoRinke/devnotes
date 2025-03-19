<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use app\Traits\ApiResponses;

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
     */
    public function handle(Request $request, Closure $next): Response {
        if (! $request->user() || ! $request->user()->hasVerifiedEmail()) {
            return $this->errorResponse('Your email address is not verified.', 'EMAIL_NOT_VERIFIED', 403);
        }
        return $next($request);
    }
}
