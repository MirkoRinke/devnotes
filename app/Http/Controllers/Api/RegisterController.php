<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\User;

use App\Rules\NotForbiddenName;

use App\Traits\ApiResponses; // example $this->successResponse($post, 'Post created successfully', 201);

use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses;

    /**
     * The validation rules for the user data
     */
    public function getValidationRules(): array {
        $validationRules = [
            'name' => ['required', 'string', 'max:255', new NotForbiddenName()],
            'email' => 'required|string|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ];
        return $validationRules;
    }

    /**
     * Register a new user
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

            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => bcrypt($validatedData['password']),
            ]);

            return $this->successResponse($user, 'User created successfully', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
