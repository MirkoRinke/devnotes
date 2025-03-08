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

use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Import the AuthorizesRequests trait

use Exception; // Import the Exception class
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UserProfileController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, ApiSorting, ApiFiltering, SelectableAttributes, ApiPagination, QueryBuilder, AuthorizesRequests;


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


    /**
     * The methods array contains the methods that are used in the buildQuery method
     */
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
            // Check if the user is authorized to view the user profiles
            $this->authorize('viewAny', UserProfile::class);

            // Check if the user profiles exist in the database           
            if (UserProfile::count() === 0) {
                return $this->successResponse([], 'No User Profiles exist in the database', 200);
            }

            // Get all the user profiles from the database
            $query = UserProfile::query();

            // Filter based on user role and profile visibility
            $user = $request->user();
            if ($user->role !== 'admin') {
                $query->where(function ($subQuery) use ($user) {
                    $subQuery->where('is_public', true)
                        ->orWhere('user_id', $user->id);
                });
            }

            // Build the query based on the request and return JsonResponse|Collection|LengthAwarePaginator
            $query = $this->buildQuery($request, $query, $this->methods);

            // Check if the query is an instance of JsonResponse and return the response
            if ($query instanceof JsonResponse) {
                return $query;
            }

            // Check if the query is empty after filtering and return the response
            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No User Profiles exist in the database', 200);
            }

            return $this->successResponse($query, 'User Profiles retrieved successfully', 200);
        } catch (Exception $e) {
            return $this->errorResponse('User Profiles not found', 'PROFILE_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Permission denied', 'PERMISSION_DENIED', 403);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse {
        return $this->errorResponse('Profiles are automatically created with users, so manual creation is not allowed', 'PROFILE_CREATION_DISABLED', 403);
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

            // Need this because the select method returns only the query object
            $query = $query->firstOrFail();

            // Check if the user is authorized to view the user profile
            $this->authorize('view', $query);

            return $this->successResponse($query, 'User Profile retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("User Profile with ID $id does not exist", 'PROFILE_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Permission denied', 'PERMISSION_DENIED', 403);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse {
        try {
            $userProfile = UserProfile::findOrFail($id);
            // Check if the user is authorized to update the user profile
            $this->authorize('update', $userProfile);

            $validatedData = $request->validate(
                $this->validationRules,
                $this->getValidationMessages()
            );

            $userProfile->update($validatedData);

            return $this->successResponse($userProfile, 'User Profile updated successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("User Profile with ID $id does not exist", 'PROFILE_NOT_FOUND', 404);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 'VALIDATION_FAILED', 422);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Permission denied', 'PERMISSION_DENIED', 403);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse {
        try {
            $userProfile = UserProfile::findOrFail($id);
            // Check if the user is authorized to delete the user profile
            $this->authorize('delete', $userProfile);

            // Profiles are automatically created with the user and should not be deleted manually
            return $this->errorResponse('Profiles are automatically managed and cannot be deleted manually', ['profile_id' => $id, 'user_id' => $request->user()->id], 405);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("User Profile with ID $id does not exist", 'PROFILE_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Permission denied', 'PERMISSION_DENIED', 403);
        }
    }
}
