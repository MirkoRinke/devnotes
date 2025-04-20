<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\User;

use App\Rules\NotForbiddenName;

use App\Traits\ApiResponses; // example $this->successResponse($post, 'Post created successfully', 201);

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
     * Note on Email Verification:
     * For this portfolio/demo project, emails are automatically verified.
     * In a production environment, the typical implementation would:
     * - Set email_verified_at to null initially
     * - Send a verification email with a token/signed link
     * - Provide an API endpoint for verification
     * - Restrict certain functionality for unverified users
     * - Implement the MustVerifyEmail interface in the User model
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request): JsonResponse {
        try {
            $validatedData = $request->validate(
                $this->getValidationRules(),
                $this->getValidationMessages()
            );

            $user = DB::transaction(function () use ($validatedData) {

                $user = User::create([
                    'name' => $validatedData['name'],
                    'display_name' => $validatedData['display_name'],
                    'email' => $validatedData['email'],
                    'password' => bcrypt($validatedData['password']),
                    'email_verified_at' => now(), // Auto-verification for demo purposes only
                ]);

                // Create profile and run moderation
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
