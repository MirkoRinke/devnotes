<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\User;

use App\Traits\ApiResponses; // example $this->successResponse($post, 'Post created successfully', 201);

use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AuthController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses;

    /**
     * Register a new user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request): JsonResponse {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse('The provided credentials are incorrect.', 'CREDENTIALS_INCORRECT', 401);
        }

        if ($user->is_banned) {
            return $this->errorResponse('Your account has been suspended.', 'ACCOUNT_SUSPENDED', 403);
        }

        $token = $user->createToken($request->device_name);
        $token->accessToken->expires_at = Carbon::now()->addDays(7);
        $token->accessToken->save();

        return $this->successResponse(['accessToken' => $token->plainTextToken, 'type' => 'Bearer'], 'Login successful', 200);
    }


    /**
     * Logout a user and revoke the token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request): JsonResponse {
        /** @var \Laravel\Sanctum\PersonalAccessToken $token */
        $token = $request->user()->currentAccessToken();
        $token->delete();
        return $this->successResponse(null, 'Logout successful', 200);
    }

    /**
     * Logout a user from all devices
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserTokens(Request $request): JsonResponse {
        $tokensCollection = $request->user()->tokens()->orderBy('last_used_at', 'desc')->get();

        $formattedTokens = $tokensCollection->map(function ($token) use ($request) {
            return [
                'id' => $token->id,
                'device_name' => $token->name,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
                'is_current' => $token->id === $request->user()->currentAccessToken()->id
            ];
        });

        return $this->successResponse($formattedTokens, 'Token list retrieved', 200);
    }

    /**
     * Revoke a device token
     *
     * @param Request $request
     * @param $tokenId
     * @return \Illuminate\Http\JsonResponse
     */
    public function revokeToken(Request $request, $tokenId): JsonResponse {
        if (!is_numeric($tokenId)) {
            return $this->errorResponse('Invalid token ID.', 'INVALID_TOKEN_ID', 400);
        }

        $token = $request->user()->tokens()->where('id', $tokenId)->first();

        if (!$token) {
            return $this->errorResponse('Token not found or does not belong to you.', 'TOKEN_NOT_FOUND', 404);
        }

        if ($token->id === $request->user()->currentAccessToken()->id) {
            return $this->errorResponse('Cannot revoke the current session.', 'CURRENT_TOKEN_REVOKE_FORBIDDEN', 403);
        }

        $token->delete();

        return $this->successResponse(null, 'Device logged out successfully', 200);
    }

    /**
     * Revoke all tokens except the current one
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function revokeAllTokens(Request $request): JsonResponse {
        $tokens = $request->user()->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->get();

        if ($tokens->isEmpty()) {
            return $this->errorResponse('No other devices to logout from.', 'NO_OTHER_DEVICES', 400);
        }

        $tokens->each->delete();

        return $this->successResponse(null, 'All other devices logged out successfully', 200);
    }
}
