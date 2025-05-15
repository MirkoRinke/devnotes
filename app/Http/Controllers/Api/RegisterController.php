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
        ];
        return $validationRules;
    }

    /**
     * Register a new user
     *
     * Endpoint: POST /register
     * 
     * Creates a new user account with the provided information. Upon successful registration,
     * a user profile is automatically generated and the username is checked against forbidden names.
     * 
     * Note on Email Verification:
     * For this portfolio/demo project, emails are automatically verified.
     * In a production environment, the typical implementation would:
     * - Set email_verified_at to null initially
     * - Send a verification email with a token/signed link
     * - Provide an API endpoint for verification
     * - Restrict certain functionality for unverified users
     * - Implement the MustVerifyEmail interface in the User model
     *
     * @group Authentication
     *
     * @bodyParam name string required The full name of the user (2-255 characters). Example: John Doe
     * @bodyParam display_name string required A unique username for display (2-255 characters). Example: johndoe
     * @bodyParam email string required A valid, unique email address. Example: john@example.com
     * @bodyParam password string required Password (min 8 characters). Example: secret123
     * @bodyParam password_confirmation string required Must match the password field. Example: secret123
     *
     * @requestBody {
     *   "name": "John Doe",
     *   "display_name": "johndoe",
     *   "email": "john@example.com",
     *   "password": "secret123",
     *   "password_confirmation": "secret123"
     * }
     * 
     * @response status=201 scenario="Success" {
     *   "status": "success",
     *   "message": "User created successfully",
     *   "code": 201,
     *   "count": 1,
     *   "data": {
     *     "name": "John Doe",
     *     "display_name": "johndoe", 
     *     "email": "john@example.com",
     *     "email_verified_at": "2025-04-29T18:35:29.000000Z",
     *     "updated_at": "2025-04-29T18:35:29.000000Z",
     *     "created_at": "2025-04-29T18:35:29.000000Z",
     *     "id": 10
     *   }
     * }
     *
     * @response status=422 scenario="Validation Error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
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
     */
    public function register(Request $request): JsonResponse {
        try {
            $validatedData = $request->validate(
                $this->getValidationRules(),
                $this->getValidationMessages('User')
            );

            $user = DB::transaction(function () use ($validatedData) {

                $user = User::create([
                    'name' => $validatedData['name'],
                    'display_name' => $validatedData['display_name'],
                    'email' => $validatedData['email'],
                    'password' => bcrypt($validatedData['password']),
                    'email_verified_at' => now(), // Auto-verification for demo purposes only
                ]);

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
}
