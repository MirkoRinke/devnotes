<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use App\Models\UserProfile;
use App\Rules\AllowedContactChannels;
use App\Rules\AllowedSocialLinks;
use App\Rules\NotForbiddenName;

use App\Traits\ApiResponses; // example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);

use App\Services\UserRelationService;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Illuminate\Auth\Access\AuthorizationException;

class UserProfileController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, QueryBuilder, AuthorizesRequests;


    /**
     * The user relation service
     */
    protected $userRelationService;


    /**
     * Constructor to initialize the services
     */
    public function __construct(UserRelationService $userRelationService) {
        $this->userRelationService = $userRelationService;
    }


    /**
     * The validation messages for the user profile data plus the forbidden name validation
     *
     * @return array
     */
    public function getValidationRulesUpdate($userProfile): array {
        $validationRules = [
            'display_name' => ['sometimes', 'required', 'unique:user_profiles,display_name,' . $userProfile->id, 'string', 'min:2', 'max:255', new NotForbiddenName()],
            'public_email' => 'sometimes|nullable|email|max:255',
            'location' => 'sometimes|nullable|string|max:255',
            'skills' => 'sometimes|nullable|array',
            'biography' => 'sometimes|nullable|string',
            'contact_channels' => ['sometimes', 'nullable', 'array', new AllowedContactChannels()],
            'social_links' => ['sometimes', 'nullable', 'array', new AllowedSocialLinks()],
            'website' => 'sometimes|nullable|string|max:255',
            'avatar_path' => 'sometimes|nullable|string|max:255',
            'is_public' => 'sometimes|required|boolean',
            'auto_load_external_images' => 'sometimes|required|boolean',
            'external_images_temp_until' => 'sometimes|nullable|date',
            'auto_load_external_videos' => 'sometimes|required|boolean',
            'external_videos_temp_until' => 'sometimes|nullable|date',
            'auto_load_external_resources' => 'sometimes|required|boolean',
            'external_resources_temp_until' => 'sometimes|nullable|date',
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
                $this->getValidationRulesUpdate($userProfile),
                $this->getValidationMessages('UserProfile')
            );

            $userProfile = DB::transaction(function () use ($userProfile, $validatedData) {

                $userProfile->update($validatedData);

                // Update Profile display name and check for forbidden words
                $this->userRelationService->updateProfileDisplayName($userProfile);

                return $userProfile;
            });

            return $this->successResponse($userProfile, 'User Profile updated successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("User Profile with ID $id does not exist", 'PROFILE_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
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


    /**
     * Enable or disable temporary external loading for images, videos, or links
     */
    public function enableTemporaryExternals(Request $request, string $id): JsonResponse {
        try {
            $userProfile = UserProfile::findOrFail($id);

            $this->authorize('update', $userProfile);

            $validatedData = $request->validate(
                [
                    'type' => 'required|string|in:images,videos,resources',
                    'hours' => 'required|integer|min:0|max:72'
                ],
                $this->getValidationMessages('UserProfile')
            );

            $type = $validatedData['type'];
            $hours = $validatedData['hours'];

            if ($hours === 0) {
                $userProfile->{"external_{$type}_temp_until"} = null;
                $userProfile->save();
                return $this->successResponse($userProfile, "Temporary $type deactivated.", 200);
            }

            $userProfile->{"external_{$type}_temp_until"} = now()->addHours($hours);
            $userProfile->save();

            return $this->successResponse($userProfile, "Temporary $type successfully activated for the next $hours hours.", 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("User Profile with ID $id does not exist", 'PROFILE_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
