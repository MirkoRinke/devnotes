<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User;
use Illuminate\Http\JsonResponse; // Import the JsonResponse class to use it in the controller example return new JsonResponse(['message' => 'User created successfully'], 201);

use App\Traits\ApiResponses; // Import the ApiResponses trait to use it in the controller example $this->successResponse($post, 'Post created successfully', 201);

use Illuminate\Validation\ValidationException; // Import the ValidationException class

class RegisterController extends Controller{

    use ApiResponses; // Use the ApiResponses trait in the controller

   /**
     * Register a new user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request): JsonResponse {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
            ],         
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
