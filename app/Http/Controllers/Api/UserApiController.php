<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User; // Import the User model to use it in the controller example User::all() or User::findOrFail($id) or User::create($data) or User::update($data) or User::delete() or User::query()
use Illuminate\Http\JsonResponse; // Import the JsonResponse class to use it in the controller example $this->successResponse($users, 'Users retrieved successfully', 200);

use App\Traits\ApiResponses; // Import the ApiResponses trait to use it in the controller example $this->successResponse($users, 'Users retrieved successfully', 200);
use App\Traits\ApiSorting; // Import the ApiSorting trait to use it in the controller example $this->sort($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiFiltering; // Import the ApiFiltering trait to use it in the controller example $this->filter($request, $query, [ 'name', 'email']);
use App\Traits\SelectableAttributes; // Import the SelectableAttributes trait to use it in the controller example $this->selectAttributes($request, $query, [ 'id','name', 'email']);


use Exception; // Import the Exception class
use Illuminate\Validation\ValidationException; // Import the ValidationException class
use Illuminate\Database\Eloquent\ModelNotFoundException; // Import the ModelNotFoundException class

class UserApiController extends Controller {

    // Use the ApiResponses, ApiSorting, ApiFiltering and SelectableAttributes traits in the controller
    use ApiResponses, ApiSorting, ApiFiltering , SelectableAttributes;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse {
        try {
            $query = User::query();

            // Sort the query results based on the request sort array
            $query = $this->sort($request, $query, [ 'id','name', 'email']);
            
            // Check return value of the sort method and return the response if status code is 400
            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }
            
            // Filter the query results based on the request filters array 
            $query = $this->filter($request, $query, [ 'name', 'email']); 

            // Check return value of the filter method and return the response if status code is 400
            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            // Select the query results based on the request select array
            $query = $this->selectAttributes($request, $query, [ 'id','name', 'email']);

            // Check return value of the selectAttributes method and return the response if status code is 400
            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            // Set the number of items to be returned based on the request per_page array or default to 10
            $perPage = $request->input('per_page') ?? 10;

            // Get the query results
            $query = $query->paginate($perPage);

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
    public function show(string $id, Request $request): JsonResponse {
        try {
        $query = User::query()->where('id', $id);

        // Select the user attributes based on the request select array
        $query = $this->selectAttributes($request, $query, ['id', 'name', 'email']);

        // Check return value of the selectAttributes method and return the response if status code is 400
        if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
            return $query;
        }

        $user = $query->firstOrFail();

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
