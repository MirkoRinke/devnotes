<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\UserProfile;

use App\Traits\ApiResponses; // Import the ApiResponses trait to use it in the controller example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\ApiSorting;  // Import the ApiSorting trait to use it in the controller example $query = $this->sort(request(), $query, ['id', 'title', 'language', 'category', 'status']);
use App\Traits\ApiFiltering; // Import the ApiFiltering trait to use it in the controller example $query = $this->filter(request(), $query, ['title', 'language', 'category', 'status']);
use App\Traits\SelectableAttributes; // Import the SelectableAttributes trait to use it in the controller example $this->selectAttributes($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiPagination; // Import the ApiPagination trait to use it in the controller example $this->getPerPage($request, $query, 10);
use App\Traits\QueryBuilder; // Import the QueryBuilder trait to use it in the controller example $this->buildQuery($request, $query, $methods);


use Exception; // Import the Exception class
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UserProfileController extends Controller {

    // Use the ApiResponses, ApiSorting, ApiFiltering , SelectableAttributes , ApiPagination and QueryBuilder traits
    use ApiResponses, ApiSorting, ApiFiltering, SelectableAttributes, ApiPagination, QueryBuilder;


    /**
     * The validation rules for the user profile data
     */
    private $validationRules = [
        'display_name' => 'required|string|max:255',
        'location' => 'nullable|string|max:255',
        'skills' => 'nullable|array',
        'biography' => 'nullable|string',
        'social_links' => 'nullable|array',
        'website' => 'nullable|string|max:255',
        'avatar_path' => 'nullable|string|max:255',
        'is_public' => 'required|boolean'
    ];

    // Query building methods configuration
    private $methods = [
        'sort' => ['id', 'user_id', 'display_name', 'location', 'created_at', 'updated_at', 'is_public'],
        'filter' => ['user_id', 'display_name', 'location', 'skills', 'is_public'],
        'select' => ['id', 'user_id', 'display_name', 'location', 'skills', 'biography', 'social_links', 'website', 'avatar_path', 'is_public', 'created_at', 'updated_at'],
        'getPerPage' => 10
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request) {
        try {
            // Check if the user profile exist in the database           
            if (UserProfile::count() === 0) {
                return $this->successResponse([], 'No User Profile exist in the database', 200);
            }

            // Get all the user profiles from the database
            $query = UserProfile::query();

            // Build the query based on the request and return JsonResponse|Collection|LengthAwarePaginator
            $query = $this->buildQuery($request, $query, $this->methods);

            // Check if the query is an instance of JsonResponse and return the response
            if ($query instanceof JsonResponse) {
                return $query;
            }

            // Check if the query is empty and return a response message
            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No User Profile exist in the database', 200);
            }

            return $this->successResponse($query, 'User Profile retrieved successfully', 200);
        } catch (Exception $e) {
            return $this->errorResponse('User Profile not found', $e->getMessage(), 404);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse {
        try {
            if (UserProfile::where('user_id', request()->user()->id())->exists()) {
                return $this->errorResponse('User profile already exists', ['user_id' => ['USER_PROFILE_EXISTS']], 409);
            }

            $validatedData = $request->validate(
                $this->validationRules,
                $this->getValidationMessages()
            );

            $validatedData['user_id'] = request()->user()->id();


            $userProfile = UserProfile::create($validatedData);

            return $this->successResponse($userProfile, 'User Profile created successfully', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, Request $request): JsonResponse {
        try {
            $query = UserProfile::query()->where('id', $id);

            // Select the user attributes based on the request select array
            $query = $this->select($request, $query, $this->methods['select']);

            // Check return value of the selectAttributes method and return the response if status code is 400
            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            $query = $query->firstOrFail();

            return $this->successResponse($query, 'User Profile retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('User Profile not found', ['id' => 'User Profile with the given ID does not exist'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse {
        try {
            $userProfile = UserProfile::findOrFail($id);

            $validatedData = $request->validate(
                $this->validationRules,
                $this->getValidationMessages()
            );

            $userProfile->update($validatedData);

            return $this->successResponse($userProfile, 'User Profile update successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('User Profile not found', ['id' => 'User Profile with the given ID does not exist'], 404);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse {
        try {
            $userProfile = UserProfile::findOrFail($id);
            $userProfile->delete();
            return $this->successResponse(null, 'User Profile deleted successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('User Profile not found', ['id' => 'User Profile with the given ID does not exist'], 404);
        }
    }
}
