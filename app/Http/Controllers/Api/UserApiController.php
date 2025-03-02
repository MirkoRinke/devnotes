<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User; // Import the User model to use it in the controller example User::all()
use Illuminate\Http\JsonResponse; // Import the JsonResponse class to use it in the controller example $this->successResponse($users, 'Users retrieved successfully', 200);

use App\Traits\ApiResponses; // Import the ApiResponses trait to use it in the controller example $this->successResponse($users, 'Users retrieved successfully', 200);
use App\Traits\ApiSorting; // Import the ApiSorting trait to use it in the controller example $this->sort($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiFiltering; // Import the ApiFiltering trait to use it in the controller example $this->filter($request, $query, [ 'name', 'email']);

use Exception; // Import the Exception class
use Illuminate\Validation\ValidationException; // Import the ValidationException class
use Illuminate\Database\Eloquent\ModelNotFoundException; // Import the ModelNotFoundException class

class UserApiController extends Controller {

    use ApiResponses; // Use the ApiResponses trait in the controller
    use ApiSorting; // Use the ApiSorting trait in the controller
    use ApiFiltering; // Use the ApiFiltering trait in the controller

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse {
        try {
            $query = User::query(); 

            $query = $this->sort($request, $query, [ 'id','name', 'email']); // AllowedColumns is an array of columns that can be sorted
            
            // Check return value of the sort method and return the response if status code is 400
            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }
            
            // Filter the query results based on the request
            $query = $this->filter($request, $query, [ 'name', 'email']); // AllowedColumns is an array of columns that can be filtered

            // Check return value of the filter method and return the response if status code is 400
            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            $query = $query->get(); // Get all the users

            // Check if the query is empty and return a response message
            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No users found with the given filters', 200);
            }

            return $this->successResponse($query, 'Users retrieved successfully', 200);
        } catch (Exception $e) {
            return $this->errorResponse('Users not found', null, 404);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse {
        try {
        $user = User::findOrFail($id); 
        return $this->successResponse($user, 'User retrieved successfully', 200);
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
