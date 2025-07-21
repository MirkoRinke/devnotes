<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\User;

use App\Rules\NotForbiddenName;

use App\Traits\ApiResponses;

use App\Services\UserRelationService;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RegisterController extends Controller {

    /**
     *  The user relation service
     */
    protected $userRelationService;

    /**
     *  Constructor
     */
    public function __construct(UserRelationService $userRelationService) {
        $this->userRelationService = $userRelationService;
    }

    /**
     *  The traits used in the controller
     */
    use ApiResponses;

    /**
     * The validation rules for the user data
     * 
     * @return array
     * 
     * @example | $this->getValidationRules()
     */
    public function getValidationRules(): array {
        $validationRules = [
            'name' => ['required', 'string', 'min:2', 'max:255', new NotForbiddenName()],
            'display_name' => ['required', 'unique:users,display_name', 'string', 'max:255', new NotForbiddenName()],
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'privacy_policy_accepted' => ['required', 'accepted'],
        ];
        return $validationRules;
    }

    /**
     * Register a new user
     *
     * Endpoint: POST /register
     * 
     * Creates a new user account with the provided information. Upon successful registration,
     * a user profile is automatically generated (with user_id and display_name), and the user receives an email for verification.
     * The fields name and display_name are checked against a blacklist (FORBIDDEN_NAME).
     *
     * @group Authentication
     *
     * @bodyParam name string required The full name of the user (2-255 characters, forbidden names not allowed). Example: John Doe
     * @bodyParam display_name string required A unique username for display (2-255 characters, forbidden names not allowed). Example: johndoe
     * @bodyParam email string required A valid, unique email address. Example: john@example.com
     * @bodyParam password string required Password (min 8 characters). Example: secret123
     * @bodyParam password_confirmation string required Must match the password field. Example: secret123
     * @bodyParam privacy_policy_accepted boolean required Must be true to proceed with registration. Example: true
     *
     * @bodyContent {
     *   "name": "John Doe",                        || required, string, min:2, max:255, forbidden names not allowed
     *   "display_name": "johndoe",                 || required, string, unique, min:2, max:255, forbidden names not allowed
     *   "email": "john@example.com",               || required, string, email, unique
     *   "password": "secret123",                   || required, string, min:8, confirmed
     *   "password_confirmation": "secret123"       || required, string, must match password
     *   "privacy_policy_accepted": true             || required, boolean, must be true
     * }
     * 
     * @response status=201 scenario="Success" {
     *   "status": "success",
     *   "message": "User created successfully",
     *   "code": 201,
     *   "count": 1,
     *   "data": {
     *     "id": 10
     *     "name": "John Doe",
     *     "display_name": "johndoe", 
     *     "email": "john@example.com",
     *     "email_verified_at": "null", // or current timestamp if email verification is disabled
     *     "privacy_policy_accepted_at": "2025-07-21T21:16:33.032980Z",
     *     "updated_at": "2025-04-29T18:35:29.000000Z",
     *     "created_at": "2025-04-29T18:35:29.000000Z",
     *   }
     * }
     *
     * @response status=422 scenario="Validation Error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "name": ["FORBIDDEN_NAME"],
     *     "display_name": ["FORBIDDEN_NAME"],
     *     "email": ["EMAIL_ALREADY_IN_USE"],
     *     "display_name": ["DISPLAY_NAME_ALREADY_IN_USE"]
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
     * Note:
     * - The fields name and display_name are checked against a blacklist (forbidden names).
     * - After registration, a verification email is sent and the user must verify their email before logging in.
     * - A user profile is automatically created with user_id and display_name.
     * - Only the shown fields are returned in the response.
     */
    public function register(Request $request): JsonResponse {
        try {
            $validatedData = $request->validate(
                $this->getValidationRules(),
                $this->getValidationMessages('User')
            );

            $user = DB::transaction(function () use ($validatedData) {


                $user = new User();
                $user->name = $validatedData['name'];
                $user->display_name = $validatedData['display_name'];
                $user->email = $validatedData['email'];
                $user->password = bcrypt($validatedData['password']);
                $user->email_verified_at = config('app.features.email_verification', true) ? null : now();
                $user->moderation_info = [];
                $user->privacy_policy_accepted_at = now();

                $user->save();

                /**
                 * Send email verification notification
                 * The user must implement the MustVerifyEmail interface
                 */
                if (config('app.features.email_verification', true)) {
                    $user->sendEmailVerificationNotification();
                }

                /**
                 * Create profile and check username
                 * The userRelationService is assumed to handle the creation of the user profile
                 * and the checking of the username against forbidden names.
                 */
                $this->userRelationService->createUserProfile($user);
                $this->userRelationService->checkUsername($user);

                return $user;
            });

            return $this->successResponse($user, 'User created successfully', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Resend the email verification notification
     *
     * Endpoint: POST /email/verification-notification
     * 
     * Resend the verification email to the authenticated user if their 
     * email hasn't been verified yet.
     *
     * @group Authentication
     *
     * @response status=200 scenario="Email sent" {
     *   "status": "success",
     *   "message": "Verification link sent",
     *   "code": 200,
     *   "count": 0,
     *   "data": null
     * }
     * 
     * @response status=200 scenario="Already verified" {
     *   "status": "success",
     *   "message": "Email already verified",
     *   "code": 200,
     *   "count": 0,
     *   "data": null
     * }
     * 
     * @response status=500 scenario="Server Error" {
     *   "status": "error",
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     */
    public function resendVerificationEmail(Request $request): JsonResponse {
        try {
            // Check if email is already verified
            if ($request->user()->hasVerifiedEmail()) {
                return $this->successResponse(null, 'Email already verified', 200);
            }

            // Resend the verification email
            $request->user()->sendEmailVerificationNotification();

            return $this->successResponse(null, 'Verification link sent', 200);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Verify the user's email address
     *
     * Endpoint: POST /email/verify
     * 
     * Verifies a user's email address using the ID and hash from 
     * the verification link. This endpoint is typically called by the frontend
     * after receiving the verification link from the email.
     *
     * @group Authentication
     *
     * @bodyParam id integer required The user ID. Example: 1
     * @bodyParam hash string required The verification hash from the email. Example: 3d8d2bb014340f7b4e8547f3381068d347c27f3e
     *
     * @bodyContent {
     *   "id": 1,
     *   "hash": "3d8d2bb014340f7b4e8547f3381068d347c27f3e"
     * }
     *
     * @response status=200 scenario="Email verified" {
     *   "status": "success",
     *   "message": "Email verified successfully",
     *   "code": 200,
     *   "count": 0,
     *   "data": null
     * }
     * 
     * @response status=400 scenario="Invalid link" {
     *   "status": "error",
     *   "message": "Invalid verification link",
     *   "code": 400,
     *   "errors": "INVALID_VERIFICATION_LINK"
     * }
     * 
     * @response status=404 scenario="User not found" {
     *   "status": "error",
     *   "message": "User not found",
     *   "code": 404,
     *   "errors": "USER_NOT_FOUND"
     * }
     * 
     * @response status=422 scenario="Validation Error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "id": ["ID_FIELD_REQUIRED"],
     *     "hash": ["HASH_FIELD_REQUIRED"]
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
    public function verifyEmail(Request $request): JsonResponse {
        try {
            // Validate the request parameters
            $validated = $request->validate(
                [
                    'id' => 'required|integer',
                    'hash' => 'required|string',
                ],
                $this->getValidationMessages('verifyEmail')
            );

            // Find the user by ID
            $user = User::findOrFail($validated['id']);

            // Verify that the hash matches
            if (!hash_equals((string) $validated['hash'], sha1($user->getEmailForVerification()))) {
                return $this->errorResponse('Invalid verification link', 'INVALID_VERIFICATION_LINK', 400);
            }

            // Check if already verified
            if ($user->hasVerifiedEmail()) {
                return $this->successResponse(null, 'Email already verified', 200);
            }

            // Mark as verified and trigger Verified event
            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }

            return $this->successResponse(null, 'Email verified successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('User not found', 'USER_NOT_FOUND', 404);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
