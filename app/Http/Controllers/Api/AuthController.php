<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\User;

use App\Traits\ApiResponses;

use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

use Exception;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses;

    /**
     * User Login
     * 
     * Endpoint: POST /login
     *
     * Authenticates a user and issues an access token for API requests.
     * The token expires after 7 days and can be used for subsequent authenticated requests.
     * If the user account is currently banned, login will be denied.
     *
     * @group User Authentication
     *
     * @bodyParam email string required User's email address. Example: user@example.com
     * @bodyParam password string required User's password. Example: secret123
     * @bodyParam device_name string required Name for the device/browser creating this token. Example: iPhone 13
     *
     * @bodyContent {
     *   "email": "user@example.com,
     *   "password": "secret123",
     *   "device_name": "iPhone 13"
     * }
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Login successful",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "accessToken": "3|Kdi93NNg0xeWkNbERRYbo7c3IZhLoVUiD1RkNhmd3d718836",
     *     "type": "Bearer"
     *   }
     * }
     * 
     * @response status=401 scenario="Invalid Credentials" {
     *   "status": "error",
     *   "message": "The provided credentials are incorrect.",
     *   "code": 401,
     *   "errors": "CREDENTIALS_INCORRECT"
     * }
     *
     * @response status=403 scenario="Banned Account" {
     *   "status": "error",
     *   "message": "Your account has been suspended.",
     *   "code": 403,
     *   "errors": "ACCOUNT_SUSPENDED"
     * }
     * 
     * @response status=422 scenario="Validation Error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "email": ["EMAIL_FIELD_REQUIRED"],
     *     "password": ["PASSWORD_FIELD_REQUIRED"],
     *     "device_name": ["DEVICE_NAME_FIELD_REQUIRED"]
     *   }
     * }
     *
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     * 
     */
    public function login(Request $request): JsonResponse {
        try {
            $request->validate(
                [
                    'email' => 'required|email',
                    'password' => 'required',
                    'device_name' => 'required',
                ],
                $this->getValidationMessages('Login')
            );

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return $this->errorResponse('The provided credentials are incorrect.', 'CREDENTIALS_INCORRECT', 401);
            }

            if ($user->is_banned && now()->lt($user->is_banned)) {
                return $this->errorResponse('Your account has been suspended.', 'ACCOUNT_SUSPENDED', 403);
            }

            $token = $user->createToken($request->device_name);
            $token->accessToken->expires_at = Carbon::now()->addDays(7);
            $token->accessToken->save();

            return $this->successResponse(['accessToken' => $token->plainTextToken, 'type' => 'Bearer'], 'Login successful', 200);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Logout
     * 
     * Endpoint: POST /logout
     *
     * Revokes the current access token, effectively logging the user out.
     * This invalidates only the token used for the current request.
     *
     * @group User Authentication
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Logout successful",
     *   "code": 200,
     *   "count": 1,
     *   "data": null
     * }
     *
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     * 
     * @authenticated
     */
    public function logout(Request $request): JsonResponse {
        try {
            /** @var \Laravel\Sanctum\PersonalAccessToken $token */
            $token = $request->user()->currentAccessToken();
            $token->delete();
            return $this->successResponse(null, 'Logout successful', 200);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Get User Tokens
     * 
     * Endpoint: GET /tokens
     *
     * Retrieves a list of all access tokens (active sessions) for the authenticated user.
     * This includes the device name, last used timestamp and whether it's the current session.
     *
     * @group User Authentication
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Token list retrieved",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 2,
     *       "device_name": "test_device",
     *       "last_used_at": null,
     *       "created_at": "2025-05-13T11:13:39.000000Z",
     *       "is_current": true
     *     },
     *     {
     *       "id": 1,
     *       "device_name": "test_device",
     *       "last_used_at": "2025-04-29T20:16:46.000000Z",
     *       "created_at": "2025-04-29T20:15:56.000000Z",
     *       "is_current": false
     *     }
     *   ]
     * }
     *
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     * 
     * @authenticated
     */
    public function getUserTokens(Request $request): JsonResponse {
        try {
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
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Revoke a Token
     * 
     * Endpoint: DELETE /tokens/{tokenId}
     *
     * Revokes a specific access token by ID, logging out that specific device/session.
     * The current token cannot be revoked using this endpoint (use /logout instead).
     *
     * @group User Authentication
     * 
     * @urlParam tokenId integer required The ID of the token to revoke. Example: 5
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Device logged out successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": null
     * }
     *
     * @response status=400 scenario="Invalid Token ID" {
     *   "status": "error",
     *   "message": "Invalid token ID.",
     *   "code": 400,
     *   "errors": "INVALID_TOKEN_ID"
     * }
     *
     * @response status=404 scenario="Token Not Found" {
     *   "status": "error",
     *   "message": "Token not found or does not belong to you.",
     *   "code": 404,
     *   "errors": "TOKEN_NOT_FOUND"
     * }
     * 
     * @response status=403 scenario="Current Token" {
     *   "status": "error",
     *   "message": "Cannot revoke the current session.",
     *   "code": 403,
     *   "errors": "CURRENT_TOKEN_REVOKE_FORBIDDEN"
     * }
     * 
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     *
     * @authenticated
     */
    public function revokeToken(Request $request, $tokenId): JsonResponse {
        try {
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
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Revoke All Tokens
     * 
     * Endpoint: DELETE /tokens
     *
     * Revokes all access tokens except the current one, logging out all other devices.
     * The current session remains active.
     *
     * @group User Authentication
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "All other devices logged out successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": null
     * }
     *
     * @response status=400 scenario="No Other Devices" {
     *   "status": "error",
     *   "message": "No other devices to logout from.",
     *   "code": 400,
     *   "errors": "NO_OTHER_DEVICES"
     * }
     *
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     * 
     * @authenticated
     */
    public function revokeAllTokens(Request $request): JsonResponse {
        try {
            $tokens = $request->user()->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->get();

            if ($tokens->isEmpty()) {
                return $this->errorResponse('No other devices to logout from.', 'NO_OTHER_DEVICES', 400);
            }

            $tokens->each->delete();

            return $this->successResponse(null, 'All other devices logged out successfully', 200);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
