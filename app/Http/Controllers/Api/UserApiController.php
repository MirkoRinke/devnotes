<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User; // Import the User model to use it in the controller example User::all()
use Illuminate\Http\JsonResponse; // Import the JsonResponse class to use it in the controller example return response()->json($users);

use App\Traits\ApiResponses; // Import the ApiResponses trait to use it in the controller example $this->successResponse($users);

use Exception; // Import the Exception class
use Illuminate\Validation\ValidationException; // Import the ValidationException class
use Illuminate\Database\Eloquent\ModelNotFoundException; // Import the ModelNotFoundException class

class UserApiController extends Controller {

    use ApiResponses; // Use the ApiResponses trait in the controller

    /**
     * Display a listing of the resource.
     */
    public function index(){
        try {
            $users = User::all();
            return $this->successResponse($users, 'Users retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse('Users not found', [], 404);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse {
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

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse {
        try {
        $user = User::findOrFail($id); 
        return $this->successResponse($user, 'User retrieved successfully');
    } catch (ModelNotFoundException $e) {
        return $this->errorResponse('User not found', ['id' => 'User with the given ID does not exist'], 404);
    }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse {
        try {
        $user = User::findOrFail($id); 
    
        $validatedData = $request->validate([ 
            'name' => 'sometimes|string|max:255', 
            'email' => 'sometimes|string|email|unique:users,email,' . $user->id, 
            'password' => 'sometimes|string|min:8|confirmed',
        ],         
        $this->getValidationMessages()
        );
    
        $user->update([ 
            'name' => $validatedData['name'] ?? $user->name, 
            'email' => $validatedData['email'] ?? $user->email, 
            'password' => isset($validatedData['password']) ? bcrypt($validatedData['password']) : $user->password,
        ]);
        
        return $this->successResponse($user, 'User update successfully', 200); 
    
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('User not found', ['id' => 'User with the given ID does not exist'], 404);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse{
        try {
            $user = User::findOrFail($id);
            $user->delete();
            return $this->successResponse(null, 'User deleted successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('User not found', ['id' => 'User with the given ID does not exist'], 404);
        }
    }
}
