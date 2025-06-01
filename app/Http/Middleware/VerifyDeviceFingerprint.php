<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

use App\Traits\ApiResponses;

/**
 * Middleware to verify device fingerprint in requests
 *
 * This middleware checks if the request contains a valid device fingerprint
 * in the X-Device-Fingerprint header. If the fingerprint is missing or does not match
 * the one stored in the user's current access token, it will delete the token
 * and return an error response.
 */
class VerifyDeviceFingerprint {

    /**
     *  The traits used in the class
     */
    use ApiResponses;

    /**
     * Handle an incoming request.
     *
     * @param Request $request The incoming HTTP request
     * @param Closure $next The next middleware in the pipeline
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     *
     * @example | $middleware->alias([
     *              'device-fingerprint' => VerifyDeviceFingerprint::class,
     *            ]);
     * 
     * Possible error responses:
     * 
     * @response status=401 scenario="Fingerprint Missing" {
     *   "status": "error",
     *   "message": "This request requires a valid device fingerprint in the X-Device-Fingerprint header",
     *   "code": 401,
     *   "errors": "DEVICE_FINGERPRINT_MISSING"
     * }
     * 
     * @response status=401 scenario="Fingerprint Mismatch" {
     *   "status": "error",
     *   "message": "The provided device fingerprint does not match the one stored in the token",
     *   "code": 401,
     *   "errors": "DEVICE_FINGERPRINT_MISMATCH"
     * }
     */
    public function handle(Request $request, Closure $next) {
        $user = $request->user();

        $headerFingerprint = $request->header('x-device-fingerprint');
        $tokenFingerprint = $user->currentAccessToken()->device_fingerprint;

        $tokenId = $user->currentAccessToken()->id;
        $token = $request->user()->tokens()->where('id', $tokenId)->first();

        if (!$headerFingerprint) {
            $token->delete();
            return $this->errorResponse('This request requires a valid device fingerprint in the X-Device-Fingerprint header', 'DEVICE_FINGERPRINT_MISSING', 401);
        }

        if ($headerFingerprint !== $tokenFingerprint) {
            $token->delete();
            return $this->errorResponse('The provided device fingerprint does not match the one stored in the token', 'DEVICE_FINGERPRINT_MISMATCH', 401);
        }

        return $next($request);
    }
}
