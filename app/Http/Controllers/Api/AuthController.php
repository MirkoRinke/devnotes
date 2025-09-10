<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\User;

use App\Traits\ApiResponses;
use App\Traits\QueryBuilder;
use App\Traits\AuthHelper;

use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;

use Exception;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, QueryBuilder, AuthHelper;

    /**
     * User Login
     * 
     * Endpoint: POST /login
     *
     * Authenticates a user and issues an access token for API requests.
     * The token expires after 30 days and can be used for subsequent authenticated requests.
     * If the user account is banned or the email is not verified, login will be denied.
     *
     * @group User Authentication
     *
     * @bodyParam email string required User's email address. Example: max@example1.com
     * @bodyParam user_name string required User's username. Example: max
     * @bodyParam password string required User's password. Example: sicheresPasswort123
     * @bodyParam device_name string required Name for the device/browser creating this token. Example: test_device1
     * @bodyParam device_fingerprint string required Unique device fingerprint (SHA256 hash). Example: 9e2f1c3a4b5d11eebe560242ac1200029e2f1c3a4b5d11eebe560242ac120002
     * @bodyParam privacy_policy_accepted boolean required Must be true to proceed with login. Example: true
     *
     * @bodyContent {
     *   "email": "max@example1.com",
     *   "password": "sicheresPasswort123",
     *   "device_name": "test_device1",
     *   "device_fingerprint": "9e2f1c3a4b5d11eebe560242ac1200029e2f1c3a4b5d11eebe560242ac120002",
     *   "privacy_policy_accepted": true
     * }
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Login successful",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "accessToken": "1|IPlBeEcUSfTJsYLOD0GpUO0DvPKbt8kY2MpOwRez07b31185",
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
     * @response status=403 scenario="Email Not Verified" {
     *   "status": "error",
     *   "message": "Your email address is not verified.",
     *   "code": 403,
     *   "errors": "EMAIL_NOT_VERIFIED"
     * }
     * 
     * @response status=422 scenario="Validation Error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "email": ["EMAIL_FIELD_REQUIRED"],
     *     "password": ["PASSWORD_FIELD_REQUIRED"],
     *     "device_name": ["DEVICE_NAME_FIELD_REQUIRED"],
     *     "device_fingerprint": ["DEVICE_FINGERPRINT_FIELD_REQUIRED"],
     *     "privacy_policy_accepted": ["PRIVACY_POLICY_ACCEPTED_FIELD_REQUIRED"],
     *   }
     * }
     *
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     */
    public function login(Request $request): JsonResponse {
        try {
            $request->validate(
                [
                    'email' => 'sometimes|required|email',
                    'user_name' => 'sometimes|required|string',
                    'password' => 'required',
                    'device_name' => 'required',
                    'device_fingerprint' => 'required|string|max:255',
                    'privacy_policy_accepted' => ['sometimes', 'accepted'],
                    'terms_of_service_accepted' => ['sometimes', 'accepted'],
                ],
                $this->getValidationMessages('Login')
            );

            if (!$request->has('email') && !$request->has('user_name')) {
                return $this->errorResponse('Either email or user_name must be provided.', 'EMAIL_OR_USERNAME_REQUIRED', 422);
            }

            if ($request->has('email')) {
                $user = User::where('email', $request->email)->first();
            } else {
                $user = User::where('name', $request->user_name)->first();
            }

            if (!$user || !Hash::check($request->password, $user->password)) {
                return $this->errorResponse('The provided credentials are incorrect.', 'CREDENTIALS_INCORRECT', 401);
            }

            /**
             * Set privacy policy and terms of service acceptance timestamps
             */
            if ($request->privacy_policy_accepted) {
                $user->privacy_policy_accepted_at = now();
            }
            if ($request->terms_of_service_accepted) {
                $user->terms_of_service_accepted_at = now();
            }
            if ($request->privacy_policy_accepted || $request->terms_of_service_accepted) {
                $user->save();
            }

            $defaultDate = Carbon::now()->subDays(2)->format('Y-m-d H:i:s');
            if (!$user->privacy_policy_accepted_at || $user->privacy_policy_accepted_at < env('CURRENT_PRIVACY_POLICY_DATE', $defaultDate)) {
                return $this->errorResponse('You must accept the latest privacy policy.', 'PRIVACY_POLICY_NOT_ACCEPTED', 403);
            }

            if (!$user->terms_of_service_accepted_at || $user->terms_of_service_accepted_at < env('CURRENT_TERMS_OF_SERVICE_DATE', $defaultDate)) {
                return $this->errorResponse('You must accept the latest terms of service.', 'TERMS_OF_SERVICE_NOT_ACCEPTED', 403);
            }

            if ($user->is_banned && now()->lt($user->is_banned)) {
                return $this->errorResponse('Your account has been suspended.', 'ACCOUNT_SUSPENDED', 403);
            }

            /**
             * Delete all tokens with the same name OR tokens that are expired
             */
            $user->tokens()->where('name', $request->device_name)->orWhere('expires_at', '<', now())->delete();

            $token = $user->createToken($request->device_name);
            $token->accessToken->expires_at = Carbon::now()->addDays(30);
            $token->accessToken->device_fingerprint = $request->device_fingerprint;
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
     * This invalidates only the token used for the current request/session.
     * The user must be authenticated to use this endpoint.
     *
     * @group User Authentication
     *
     * @authenticated
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Logout successful",
     *   "code": 200,
     *   "count": 0,
     *   "data": null
     * }
     *
     * @response status=404 scenario="Unauthorized" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 404,
     *   "errors": "UNAUTHORIZED"
     * }
     *
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
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
     * List User Tokens (Sessions)
     * 
     * Endpoint: GET /tokens
     *
     * Retrieves a list of all access tokens (active sessions) for the authenticated user.
     * Supports filtering, sorting, field selection und pagination.
     * 
     * @group User Authentication
     *
     * @queryParam select   See [ApiSelectable](#apiselectable) for field selection details. 
     * @see \App\Traits\ApiSelectable::select()
     * 
     * @queryParam sort     See [ApiSorting](#apisorting) for sorting details.
     * @see \App\Traits\ApiSorting::sort()
     * 
     * @queryParam filter   See [ApiFiltering](#apifiltering) for filtering details. 
     * @see \App\Traits\ApiFiltering::filter()
     * 
     * @queryParam page     Pagination, see [ApiPagination](#apipagination).
     * @see \App\Traits\ApiPagination::paginate()
     * 
     * @queryParam per_page Pagination, see [ApiPagination](#apipagination). 
     * @see \App\Traits\ApiPagination::paginate()
     * 
     * Example URL: /tokens
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Token list retrieved",
     *   "code": 200,
     *   "count": 2,
     *   "data": [
     *     {
     *       "id": 2,
     *       "name": "test_device2",
     *       "last_used_at": "2025-05-13T11:13:39.000000Z",
     *       "created_at": "2025-05-13T11:13:39.000000Z",
     *       "is_current": true                                 || Virtual field, calculated dynamically
     *     },
     *     {
     *       "id": 1,
     *       "name": "test_device1",
     *       "last_used_at": "2025-04-29T20:16:46.000000Z",
     *       "created_at": "2025-04-29T20:15:56.000000Z",
     *       "is_current": false                                || Virtual field, calculated dynamically
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
     * Note:  
     * - The `is_current` field indicates whether the token is the one currently being used for the API request.  
     * - This field is calculated dynamically and cannot be used for filtering.
     * 
     * @authenticated
     */
    public function getUserTokens(Request $request): JsonResponse {
        try {
            $tokensRelation = $request->user()->tokens()->select(['id', 'name', 'last_used_at', 'created_at', 'updated_at']);

            $query = $tokensRelation->getQuery();

            $originalSelectFields = $this->getSelectFields($request);

            $this->modifyRequestSelect($request, ['id'], ['is_current']);

            $query = $this->buildQuery($request, $query, 'user_tokens');
            if ($query instanceof JsonResponse) {
                return $query;
            }

            $query = $this->setCurrentToken($request, $query, $originalSelectFields);

            $query = $this->setVisibleTokensFields($query);

            return $this->successResponse($query, 'Token list retrieved', 200);
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
     *   "count": 0,
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
     *   "count": 0,
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


    /**
     * Forgot Password
     * 
     * Endpoint: POST /forgot-password
     *
     * Sends a password reset link to the user's email address.
     * The email will contain a token that can be used to reset the password.
     * During development, the email is saved to the log file.
     *
     * @group User Authentication
     *
     * @bodyParam email string required The email address of the user. Example: user@example.com
     *
     * @bodyContent {
     *   "email": "user@example.com"
     * }
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Password reset link sent",
     *   "code": 200,
     *   "count": 0,
     *   "data": null
     * }
     * 
     * @response status=422 scenario="Validation Error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "email": ["EMAIL_FIELD_REQUIRED"]
     *   }
     * }
     *
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     */
    public function forgotPassword(Request $request): JsonResponse {
        try {
            $request->validate(
                ['email' => 'required|string|email'],
                $this->getValidationMessages('ForgotPassword')
            );

            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status === Password::RESET_LINK_SENT) {
                return $this->successResponse(null, 'Password reset link sent', 200);
            }

            return $this->errorResponse('Password reset failed', 'PASSWORD_RESET_FAILED', 400);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Reset Password
     * 
     * Endpoint: POST /reset-password
     *
     * Resets the user's password using the token received in the email.
     *
     * @group User Authentication
     *
     * @bodyParam token string required The token from the reset link. Example: 5b39dcaa83dafb56581ad1c3c8335984eb666e9a29e46c171833e257ec60cbfd
     * @bodyParam email string required The email address of the user. Example: user@example.com
     * @bodyParam password string required The new password. Example: newSecurePassword123
     * @bodyParam password_confirmation string required Password confirmation. Example: newSecurePassword123
     *
     * @bodyContent {
     *   "token": "5b39dcaa83dafb56581ad1c3c8335984eb666e9a29e46c171833e257ec60cbfd",
     *   "email": "user@example.com",
     *   "password": "newSecurePassword123",
     *   "password_confirmation": "newSecurePassword123"
     * }
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Password has been reset",
     *   "code": 200,
     *   "count": 0,
     *   "data": null
     * }
     * 
     * @response status=400 scenario="Invalid Token" {
     *   "status": "error",
     *   "message": "This password reset token is invalid.",
     *   "code": 400,
     *   "errors": "PASSWORD_RESET_FAILED"
     * }
     * 
     * @response status=422 scenario="Validation Error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "email": ["EMAIL_FIELD_REQUIRED"],
     *     "password": ["PASSWORD_FIELD_REQUIRED"],
     *     "token": ["TOKEN_FIELD_REQUIRED"]
     *   }
     * }
     *
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     */
    public function resetPassword(Request $request): JsonResponse {
        try {
            $request->validate(
                [
                    'token' => 'required|string',
                    'email' => 'required|string|email',
                    'password' => 'required|string|min:8|confirmed',
                ],
                $this->getValidationMessages('ResetPassword')
            );

            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password)
                    ]);

                    $user->tokens()->delete();

                    $user->save();

                    event(new PasswordReset($user));
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return $this->successResponse(null, 'Password has been reset', 200);
            }

            return $this->errorResponse('Password reset failed', 'PASSWORD_RESET_FAILED', 400);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
