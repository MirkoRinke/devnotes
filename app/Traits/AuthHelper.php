<?php

namespace App\Traits;

use Illuminate\Http\Request;

use Laravel\Sanctum\PersonalAccessToken;

trait AuthHelper {
    /**
     * 
     * Extract user from the authorization bearer token
     * 
     * @param Request $request The HTTP request containing the bearer token
     * @return mixed|null Returns the authenticated user model instance if 
     *                    a valid token exists, null otherwise
     */
    protected function getUserFromToken(Request $request) {
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            return null;
        }

        $token = PersonalAccessToken::findToken($bearerToken);

        return $token ? $token->tokenable->load('profile') : null;
    }
}
