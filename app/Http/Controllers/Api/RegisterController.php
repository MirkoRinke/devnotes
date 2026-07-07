<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\User;

use App\Rules\NotForbiddenName;

use App\Traits\ApiResponses;

use App\Services\UserRelationService;
use App\Services\SecurityNotificationService;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller {

    /**
     *  The user relation service
     */
    protected $userRelationService;

    /**
     *  The security notification service
     */
    protected $securityNotificationService;

    /**
     *  Constructor
     */
    public function __construct(UserRelationService $userRelationService, SecurityNotificationService $securityNotificationService) {
        $this->userRelationService = $userRelationService;
        $this->securityNotificationService = $securityNotificationService;
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
            'name' => ['required', 'unique:users,name', 'string', 'min:2', 'max:40', new NotForbiddenName(), 'regex:/^[a-zA-Z0-9._ -]{2,}$/', 'not_regex:/\s{2,}/',],
            'display_name' => ['required', 'unique:users,display_name', 'string', 'min:2', 'max:40', new NotForbiddenName(), 'regex:/^[a-zA-Z0-9._ -]{2,}$/', 'not_regex:/\s{2,}/'],
            'email' => 'required|string|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->max(255)->letters()->mixedCase()->numbers()->symbols()->uncompromised()],
            'privacy_policy_accepted' => ['required', 'accepted'],
            'terms_of_service_accepted' => ['required', 'accepted'],
        ];
        return $validationRules;
    }



    /**
     * The validation rules for checking availability of username and display name
     * 
     * @return array
     * 
     * @example | $this->getCheckAvailabilityValidationRules()
     */
    public function getCheckAvailabilityValidationRules(): array {
        $validationRules = [
            'name' => ['sometimes', 'string', 'min:2', 'max:40', new NotForbiddenName(), 'regex:/^[a-zA-Z0-9._ -]{2,}$/', 'not_regex:/\s{2,}/'],
            'display_name' => ['sometimes', 'string', 'min:2', 'max:40', new NotForbiddenName(), 'regex:/^[a-zA-Z0-9._ -]{2,}$/', 'not_regex:/\s{2,}/'],
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
     * The fields name and display_name are checked against a blacklist (FORBIDDEN_NAME and FORBIDDEN_DISPLAY_NAME).
     *
     * @group Authentication
     *
     * @bodyParam name string required The full name of the user (2-40 characters, forbidden names not allowed). Example: John Doe
     * @bodyParam display_name string required A unique username for display (2-40 characters, forbidden names not allowed). Example: johndoe
     * @bodyParam email string required A valid, unique email address. Example: john@example.com
     * @bodyParam password string required Password (8-255 characters, must contain uppercase and lowercase letters, numbers, and symbols, and must not be found in data breaches). Example: sicheresPasswort1234!
     * @bodyParam password_confirmation string required Must match the password field. Example: sicheresPasswort1234!
     * @bodyParam privacy_policy_accepted boolean required Must be true to proceed with registration. Example: true
     * @bodyParam terms_of_service_accepted boolean required Must be true to proceed with registration. Example: true
     *
     * @bodyContent {
     *   "name": "John Doe",                                || required, string, min:2, max:40, forbidden names not allowed, regex:/^[a-zA-Z0-9._-]{2,}$/
     *   "display_name": "johndoe",                         || required, string, unique, min:2, max:40, forbidden names not allowed, regex:/^[a-zA-Z0-9._-]{2,}$/
     *   "email": "john@example.com",                       || required, string, email, unique
     *   "password": "sicheresPasswort1234!",               || required, string, min:8, confirmed
     *   "password_confirmation": "sicheresPasswort1234!"   || required, string, must match password
     *   "privacy_policy_accepted": true                    || required, boolean, must be true
     *   "terms_of_service_accepted": true                  || required, boolean, must be true
     * }
     * 
     * @response status=201 scenario="Success" {
     *   "status": "success",
     *   "message": "User created successfully",
     *   "code": 201,
     *   "count": 1,
     *   "data": {
     *    "data": null
     *   }
     * }
     *
     * @response status=422 scenario="Validation Error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "name": ["FORBIDDEN_NAME"],
     *     "display_name": ["FORBIDDEN_DISPLAY_NAME"],
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
                $user->avatar_items = [
                    'duck' => null,
                    'head_accessory' => null,
                    'eye_accessory' => null,
                    'ear_accessory' => null,
                    'neck_accessory' => null,
                    'chest_accessory' => null,
                    'background' => null,
                ];
                $user->email_verified_at = config('app.features.email_verification', true) ? null : now();
                $user->moderation_info = [];
                $user->privacy_policy_accepted_at = now();
                $user->terms_of_service_accepted_at = now();

                $user->save();

                /**
                 * Send email verification notification
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

            return $this->successResponse(null, 'User created successfully', 201);
        } catch (ValidationException $e) {
            usleep(random_int(100000, 300000));
            $responseErrors = $this->allowedErrorResponse($e);
            if (!empty($responseErrors)) {
                return $this->errorResponse('Validation failed', $responseErrors, 422, true);
            }

            $result = $this->securityNotificationService->handleEmailConflict($e->errors(), $request->input('email'));
            if ($result instanceof \Illuminate\Http\JsonResponse) {
                return $result;
            }

            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', $e->getMessage(), 500);
        }
    }


    /**
     * Check the availability of a username and display name
     * 
     * Endpoint: POST /check-registration-availability
     * 
     * This endpoint checks if the provided username and display name are available for registration. It validates the input and returns a JSON response indicating the availability of each field.
     * 
     * @bodyParam name string The username to check for availability. Example: johndoe
     * @bodyParam display_name string The display name to check for availability. Example: John Doe
     * 
     */
    public function checkRegistrationAvailability(Request $request): JsonResponse {
        try {
            $validatedData = $request->validate(
                $this->getCheckAvailabilityValidationRules(),
                $this->getValidationMessages('User')
            );

            $availability = [];

            if (isset($validatedData['name'])) {
                $nameExists = User::where('name', $validatedData['name'])->exists();
                $availability['name'] = $nameExists ? ['NAME_ALREADY_IN_USE'] : ['NAME_AVAILABLE'];
            }

            if (isset($validatedData['display_name'])) {
                $displayNameExists = User::where('display_name', $validatedData['display_name'])->exists();
                $availability['display_name'] = $displayNameExists ? ['DISPLAY_NAME_ALREADY_IN_USE'] : ['DISPLAY_NAME_AVAILABLE'];
            }

            if (!isset($validatedData['name']) && !isset($validatedData['display_name'])) {
                return $this->errorResponse('At least one field (name or display_name) is required.', 'MISSING_REQUIRED_FIELDS', 422);
            }

            return $this->successResponse($availability, 'Availability checked successfully', 200);
        } catch (ValidationException $e) {
            usleep(random_int(100000, 300000));
            $responseErrors = $this->allowedErrorResponse($e);
            if (!empty($responseErrors)) {
                return $this->errorResponse('Validation failed', $responseErrors, 422, true);
            }

            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', $e->getMessage(), 500);
        }
    }


    /**
     * Filter validation errors to only include allowed error codes for specific fields.
     */
    public function allowedErrorResponse(ValidationException $e): array {
        $errors = $e->errors();

        $allowedErrorsMap = [
            'name' => ['NAME_ALREADY_IN_USE', 'FORBIDDEN_NAME'],
            'display_name' => ['DISPLAY_NAME_ALREADY_IN_USE', 'FORBIDDEN_DISPLAY_NAME'],
            'password' => ['PASSWORD_MUST_BE_UNCOMPROMISED'],
        ];

        $responseErrors = [];
        foreach ($allowedErrorsMap as $field => $allowedCodes) {
            if (isset($errors[$field])) {
                $matches = array_intersect($errors[$field], $allowedCodes);
                if (!empty($matches)) {
                    $responseErrors[$field] = array_values($matches);
                }
            }
        }
        return $responseErrors;
    }


    /**
     * Send verification email to a specific user.
     * 
     * This endpoint is intended for admin use to (re)send the verification email
     *
     * Endpoint: POST /admin/send-verification-email
     *
     * @bodyParam id integer required The user ID. Example: 1
     *
     * @response status=200 scenario="Email sent" {
     *   "status": "success",
     *   "message": "Verification link sent",
     *   "code": 200,
     *   "count": 0,
     *   "data": null
     * }
     * @response status=404 scenario="User not found" {
     *   "status": "error",
     *   "message": "User not found",
     *   "code": 404,
     *   "errors": "USER_NOT_FOUND"
     * }
     * @response status=200 scenario="Already verified" {
     *   "status": "success",
     *   "message": "Email already verified",
     *   "code": 200,
     *   "count": 0,
     *   "data": null
     * }
     * @response status=500 scenario="Server Error" {
     *   "status": "error",
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     */
    public function adminSendVerificationEmail(Request $request): JsonResponse {
        try {
            $validated = $request->validate([
                'id' => 'required|integer|exists:users,id',
            ]);

            if ($request->user()->role !== 'admin') {
                return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
            }

            $user = User::findOrFail($validated['id']);

            if ($user->hasVerifiedEmail()) {
                return $this->successResponse(null, 'Email already verified', 200);
            }

            $user->sendEmailVerificationNotification();

            return $this->successResponse(null, 'Verification link sent', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('User not found', 'USER_NOT_FOUND', 404);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', $e->getMessage(), 500);
        }
    }

    /**
     * Resend the email verification notification
     *
     * Endpoint: POST /email/resend-verification-email
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
            if ($request->user()->hasVerifiedEmail()) {
                return $this->successResponse(null, 'Email already verified', 200);
            }

            $request->user()->sendEmailVerificationNotification();

            return $this->successResponse(null, 'Verification link sent', 200);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', $e->getMessage(), 500);
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
            $validated = $request->validate(
                [
                    'id' => 'required|integer',
                    'hash' => 'required|string',
                ],
                $this->getValidationMessages('verifyEmail')
            );

            usleep(random_int(100000, 300000));

            $user = User::findOrFail($validated['id']);

            if (!hash_equals((string) $validated['hash'], sha1($user->getEmailForVerification()))) {
                return $this->errorResponse('Invalid verification link', 'INVALID_VERIFICATION_LINK', 400);
            }

            if ($user->hasVerifiedEmail()) {
                return $this->successResponse(null, 'Email already verified', 200);
            }

            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }

            return $this->successResponse(null, 'Email verified successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Invalid verification link', 'INVALID_VERIFICATION_LINK', 400);
        } catch (ValidationException $e) {
            usleep(random_int(100000, 300000));
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', $e->getMessage(), 500);
        }
    }
}
