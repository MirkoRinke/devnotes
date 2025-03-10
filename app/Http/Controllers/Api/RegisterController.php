<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\User;

use App\Traits\ApiResponses; // example $this->successResponse($post, 'Post created successfully', 201);

use Illuminate\Validation\ValidationException;

class RegisterController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses;

    /**
     * The validation rules for the user profile data
     */
    private $validationRules = [
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|unique:users,email',
        'password' => 'required|string|min:8|confirmed',
    ];

    /**
     * Register a new user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request): JsonResponse {
        try {
            $validatedData = $request->validate(
                $this->validationRules,
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
        }
    }
}
