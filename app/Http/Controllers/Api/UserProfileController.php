<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\UserProfile;
use App\Rules\AllowedContactChannels;
use App\Rules\AllowedSocialLinks;
use App\Rules\NotForbiddenName;

use App\Traits\ApiResponses; // example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\ApiSorting;  // example $query = $this->sort(request(), $query, ['id', 'title', 'language', 'category', 'status']);
use App\Traits\ApiFiltering; // example $query = $this->filter(request(), $query, ['title', 'language', 'category', 'status']);
use App\Traits\ApiSelectable; // example $this->selectAttributes($request, $query, [ 'id','name', 'email']);
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
    use ApiResponses, ApiSorting, ApiFiltering, ApiSelectable, ApiPagination, QueryBuilder, AuthorizesRequests;


    /**
     * The validation messages for the user profile data plus the forbidden name validation
     *
     * @return array
     */
    public function getValidationRules($userProfile): array {
        $validationRules = [
            'display_name' => ['required', 'unique:user_profiles,display_name,' . $userProfile->id, 'string', 'max:255', new NotForbiddenName()],
            'public_email' => 'nullable|email|max:255',
            'location' => 'nullable|string|max:255',
            'skills' => 'nullable|array',
            'biography' => 'nullable|string',
            'contact_channels' => ['nullable', 'array', new AllowedContactChannels()],
            'social_links' => ['nullable', 'array', new AllowedSocialLinks()],
            'website' => 'nullable|string|max:255',
            'avatar_path' => 'nullable|string|max:255',
            'is_public' => 'required|boolean'
        ];
        return $validationRules;
    }

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

            $query = $this->buildQuery($request, $query, 'user_profile');

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

            $query = $this->buildQuerySelect($request, $query, 'user_profile');

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
                $this->getValidationRules($userProfile),
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
        return $this->errorResponse('Profiles are automatically managed and cannot be deleted manually', 'PROFILE_DELETE_DISABLED', 405);
    }
}
