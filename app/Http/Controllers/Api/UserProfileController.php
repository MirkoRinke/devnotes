<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\UserProfile;

use App\Traits\ApiResponses; // example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\ApiSorting;  // example $query = $this->sort(request(), $query, ['id', 'title', 'language', 'category', 'status']);
use App\Traits\ApiFiltering; // example $query = $this->filter(request(), $query, ['title', 'language', 'category', 'status']);
use App\Traits\SelectableAttributes; // example $this->selectAttributes($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiPagination; // example $this->getPerPage($request, $query, 10);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Illuminate\Auth\Access\AuthorizationException;

class UserProfileController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, ApiSorting, ApiFiltering, SelectableAttributes, ApiPagination, QueryBuilder, AuthorizesRequests;


    /**
     * The validation rules for the user profile data
     */
    private $validationRules = [
        'display_name' => 'required|unique:user_profiles|string|max:255',
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
            if (UserProfile::count() === 0) {
                return $this->successResponse([], 'No User Profiles exist in the database', 200);
            }

            $query = UserProfile::query();

            /**
             * Check if the user is an admin and return all user profiles
             * If the user is not an admin, return only public user profiles and the user's own profile
             */
            $user = $request->user();
            if ($user->role !== 'admin') {
                $query->where(function ($subQuery) use ($user) {
                    $subQuery->where('is_public', true)
                        ->orWhere('user_id', $user->id);
                });
            }

            $query = $this->buildQuery($request, $query, $this->methods);

            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No User Profiles exist in the database', 200);
            }

            return $this->successResponse($query, 'User Profiles retrieved successfully', 200);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(): JsonResponse {
        return $this->errorResponse('Profiles are automatically created with users, so manual creation is not allowed', 'PROFILE_CREATION_DISABLED', 403);
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id, Request $request): JsonResponse {
        try {
            $query = UserProfile::query()->where('id', $id);

            $query = $this->select($request, $query, $this->methods['select']);

            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            // Need this because the select method returns only the query object
            $query = $query->firstOrFail();

            $this->authorize('view', $query);

            return $this->successResponse($query, 'User Profile retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("User Profile with ID $id does not exist", 'PROFILE_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse {
        try {
            $userProfile = UserProfile::findOrFail($id);

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
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse {
        try {
            $userProfile = UserProfile::findOrFail($id);

            $this->authorize('delete', $userProfile);

            return $this->errorResponse('Profiles are automatically managed and cannot be deleted manually', 'PROFILE_DELETE_DENIED', 405);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("User Profile with ID $id does not exist", 'PROFILE_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
