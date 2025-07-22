<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * Trait AuthHelper
 * 
 * This trait provides methods to handle user authentication and token management
 * in a Laravel application using Sanctum.
 * 
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

    /**
     * Set the current token flag for each token in the list
     *
     * This method marks the current access token with a flag `is_current` to indicate
     * which token is currently active for the user.
     *
     * @param Request $request
     * @param mixed $query
     * @return mixed
     * 
     * @example | $query = $this->setCurrentToken($request, $query);
     */
    protected function setCurrentToken(Request $request, $query, $originalSelectFields): mixed {

        if (!$request->has('select') || in_array('is_current', $originalSelectFields)) {

            $currentTokenId = $request->user()->currentAccessToken()->id;

            if ($query instanceof LengthAwarePaginator) {
                foreach ($query->items() as $token) {
                    $token->is_current = ($token->id == $currentTokenId);
                }
            } else {
                foreach ($query as $token) {
                    $token->is_current = ($token->id == $currentTokenId);
                }
            }

            return $query;
        }

        return $query;
    }

    /**
     * Filter token objects to only include safe fields
     *
     * Ensures that only non-sensitive fields are included in API responses
     * by using Laravel's built-in attribute visibility control.
     *
     * @param mixed $query The query result containing token objects
     * @return mixed The same query with only safe fields visible
     * 
     * @example | $query = $this->setVisibleTokensFields($query);
     */
    protected function setVisibleTokensFields($query): mixed {
        $visibleFields = ['id', 'name', 'last_used_at', 'created_at', 'updated_at', 'is_current'];

        if ($query instanceof LengthAwarePaginator) {
            foreach ($query->items() as $token) {
                $token->setVisible($visibleFields);
            }
        } else {
            foreach ($query as $token) {
                $token->setVisible($visibleFields);
            }
        }

        return $query;
    }
}
