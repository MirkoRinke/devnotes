<?php

namespace App\Traits;

use Illuminate\Http\Request;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * This AuthHelper Trait provides a method to get the authenticated user
 * It checks if the user is authenticated via Sanctum session or Bearer token
 * and returns the authenticated user.
 */
trait AuthHelper {

    /**
     * Get authenticated user from either Sanctum session or Bearer token
     * 
     * First checks if a user is authenticated via Sanctum's built-in auth.
     * If not, attempts to authenticate via Bearer token.
     * 
     * @param Request $request The HTTP request
     * @return mixed|null The authenticated user or null
     * 
     * @example | $user = $this->getAuthenticatedUser($request);
     */
    protected function getAuthenticatedUser(Request $request) {
        // Check if the user is authenticated via Sanctum session then return it
        if ($request->user()) {
            return $request->user();
        }

        static $cachedUser = null;

        if ($cachedUser !== null) {
            return $cachedUser;
        }

        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            return $cachedUser = null;
        }

        $token = PersonalAccessToken::findToken($bearerToken);

        return $cachedUser = $token ? $token->tokenable : null;
    }
}
