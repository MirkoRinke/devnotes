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
use App\Traits\RelationLoader;
use App\Traits\ApiInclude;
use App\Traits\FieldManager;

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
    use ApiResponses, QueryBuilder, AuthorizesRequests, RelationLoader, ApiInclude, FieldManager;


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
            'website' => 'sometimes|nullable|string|max:255',
            'avatar_path' => 'sometimes|nullable|string|max:255',
            'is_public' => 'sometimes|required|boolean',
            'location' => 'sometimes|nullable|string|max:255',
            'biography' => 'sometimes|nullable|string',
            'skills' => 'sometimes|nullable|array',
            'social_links' => ['sometimes', 'nullable', 'array', new AllowedSocialLinks()],
            'contact_channels' => ['sometimes', 'nullable', 'array', new AllowedContactChannels()],
            'auto_load_external_images' => 'sometimes|required|boolean',
            'auto_load_external_videos' => 'sometimes|required|boolean',
            'auto_load_external_resources' => 'sometimes|required|boolean',
        ];
        return $validationRules;
    }


    /**
     * Setup the UserProfile query
     * This method is used to setup the query for the UserProfile model
     * It applies sorting, filtering, selecting, and pagination
     * It also loads the relations for the UserProfile model
     * 
     * @param Request $request The request object
     * @param mixed $query The query object
     * @param string $methods The method to call for the query
     * @return mixed The modified query object
     * 
     */
    protected function setupUserProfileQuery(Request $request, $query, $methods): mixed {
        $relationKeyFields = $this->getRelationKeyFields($request, ['user' => 'user_id']);

        $this->modifyRequestSelect($request, [...['id'], ...$relationKeyFields]);

        $query = $this->loadRelations($request, $query, [
            ['relation' => 'user', 'foreignKey' => 'user_id', 'columns' => $this->getRelationFieldsFromRequest($request, 'user', [], ['id', 'display_name', 'role', 'created_at', 'updated_at', 'is_banned', 'was_ever_banned', 'moderation_info'])],
        ]);

        $query = $this->$methods($request, $query, 'user_profile');
        if ($query instanceof JsonResponse) {
            return $query;
        }

        return $query;
    }

    /**
     * List User Profiles
     * 
     * Endpoint: GET /user-profiles
     *
     * Retrieves a list of user profiles. For regular users, this returns only public profiles
     * plus their own profile. Administrators and moderators can see all profiles.
     *
     * @group User Profiles
     *
     * @queryParam select string Optional. Select specific fields. Example: select=id,display_name,website
     * @queryParam sort string Optional. Sort by fields (prefix with - for descending). Example: sort=-created_at
     * @queryParam filter string Optional. Filter by fields. Example: filter[is_public]=true
     * 
     * @queryParam startsWith[field] string Optional. Filter records where a field starts with a specific value. Example: startsWith[created_at]=2023-01-01
     * @queryParam endsWith[field] string Optional. Filter records where a field ends with a specific value. Example: endsWith[public_email]=@example.com
     * 
     * @queryParam page int Optional. Page number for pagination. Example: page=1
     * @queryParam per_page int Optional. Items per page for pagination. Example: per_page=15
     * 
     * @queryParam include string Optional. Include related resources: user. Example: include=user
     * @queryParam user_fields string When including user relation, specify fields to return. 
     *                              Available fields: id, name, display_name, role, created_at, updated_at
     *                              Example: user_fields=id,name,display_name
     * 
     *  Example URL: /user-profiles
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "User Profiles retrieved successfully",
     *   "code": 200,
     *   "count": 2,
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 1,
     *       "display_name": "John Dev",
     *       "public_email": "john.public@example.com",
     *       "website": "https://johndev.example.com",
     *       "avatar_path": "/storage/avatars/johndev.jpg",
     *       "is_public": true,
     *       "location": "San Francisco, CA",
     *       "biography": "Full-stack developer with 5 years of experience",
     *       "skills": ["PHP", "Laravel", "JavaScript"],
     *       "social_links": {
     *         "github": "https://github.com/johndev",
     *         "linkedin": "https://linkedin.com/in/john-developer"
     *       },
     *       "contact_channels": {"discord": "johndev#1234"},
     *       "auto_load_external_images": false,
     *       "external_images_temp_until": null,
     *       "auto_load_external_videos": false,
     *       "external_videos_temp_until": null,
     *       "auto_load_external_resources": false,
     *       "external_resources_temp_until": null,
     *       "created_at": "2023-01-15T09:24:12.000000Z",
     *       "updated_at": "2023-02-20T14:35:47.000000Z"
     *     },
     *     {
     *       "id": 2,
     *       "user_id": 2,
     *       "display_name": "Jane Designer",
     *       "public_email": "jane.public@example.com",
     *       "website": "https://janedesigner.example.com",
     *       "avatar_path": "/storage/avatars/janedesigner.jpg",
     *       "is_public": true,
     *       "location": "Berlin, Germany",
     *       "biography": "UI/UX designer focused on user experience",
     *       "skills": ["UI/UX", "Figma", "CSS"],
     *       "social_links": {
     *         "github": "https://github.com/janedesigner",
     *         "linkedin": "https://linkedin.com/in/jane-designer"
     *       },
     *       "contact_channels": {"discord": "janedesigner#5678"},
     *       "auto_load_external_images": true,
     *       "external_images_temp_until": null,
     *       "auto_load_external_videos": false,
     *       "external_videos_temp_until": "2023-06-15T18:00:00.000000Z",
     *       "auto_load_external_resources": false,
     *       "external_resources_temp_until": null,
     *       "created_at": "2023-02-10T11:42:23.000000Z",
     *       "updated_at": "2023-05-18T09:15:32.000000Z"
     *     }
     *   ]
     * }
     *
     * @response status=200 scenario="No Profiles" {
     *   "status": "success",
     *   "message": "No User Profiles exist in the database",
     *   "code": 200,
     *   "count": 0,
     *   "data": []
     * }
     * 
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     *
     * @authenticated
     */
    public function index(Request $request) {
        try {
            if (UserProfile::count() === 0) {
                return $this->successResponse([], 'No User Profiles exist in the database', 200);
            }

            $user = $request->user();

            /**
             * Check if the user is an admin or moderator get all user profiles
             * Otherwise, get only public profiles
             */
            if ($user->role === 'admin' || $user->role === 'moderator') {
                $query = UserProfile::query();
            } else {
                $query = UserProfile::query()->where(function ($subQuery) use ($user) {
                    $subQuery->where('is_public', true)->orWhere('user_id', $user->id);
                });
            }

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupUserProfileQuery($request, $query, 'buildQuery');
            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No User Profiles exist in the database', 200);
            }

            $query = $this->manageUserProfilesFieldVisibility($request, $query);

            $query = $this->checkForIncludedRelations($request, $query);

            $query = $this->controlVisibleFields($request, $originalSelectFields, $query);

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
     * Get User Profile
     * 
     * Endpoint: GET /user-profiles/{id}
     *
     * Retrieves details for a specific user profile. Users can only access profiles that are 
     * either public or their own. Administrators and moderators can access any profile.
     *
     * @group User Profiles
     *
     * @urlParam id required The ID of the user profile. Example: 1
     *
     * @queryParam select string Optional. Select specific fields. Example: select=id,display_name,website
     * @queryParam include string Optional. Include related resources: user. Example: include=user
     * @queryParam user_fields string When including user relation, specify fields to return. 
     *                              Available fields: id, name, display_name, role, created_at, updated_at
     *                              Example: user_fields=id,name,display_name
     * 
     * Example URL: /user-profiles/1
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "User Profile retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "display_name": "John Dev",
     *     "public_email": "john.public@example.com",
     *     "website": "https://johndev.example.com",
     *     "avatar_path": "/storage/avatars/johndev.jpg",
     *     "is_public": true,
     *     "location": "San Francisco, CA",
     *     "biography": "Full-stack developer with 5 years of experience",
     *     "skills": ["PHP", "Laravel", "JavaScript"],
     *     "social_links": {
     *       "github": "https://github.com/johndev",
     *       "linkedin": "https://linkedin.com/in/john-developer"
     *     },
     *     "contact_channels": {"discord": "johndev#1234"},
     *     "auto_load_external_images": false,
     *     "external_images_temp_until": null,
     *     "auto_load_external_videos": false,
     *     "external_videos_temp_until": null,
     *     "auto_load_external_resources": false,
     *     "external_resources_temp_until": null,
     *     "reports_count": 0,          || Admin and Moderator only
     *     "created_at": "2023-01-15T09:24:12.000000Z",
     *     "updated_at": "2023-02-20T14:35:47.000000Z"
     *   }
     * }
     *
     * @response status=404 scenario="Not Found" {
     *   "status": "error",
     *   "message": "User Profile with ID 999 does not exist",
     *   "code": 404,
     *   "errors": "PROFILE_NOT_FOUND"
     * }
     *
     * @response status=403 scenario="Unauthorized" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     * 
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     *
     * @authenticated
     */
    public function show(string $id, Request $request): JsonResponse {
        try {
            $query = UserProfile::query()->where('id', $id);

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupUserProfileQuery($request, $query, 'buildQuerySelect');
            if ($query instanceof JsonResponse) {
                return $query;
            }

            // Need this because the buildQuerySelect method returns only the query object
            $query = $query->firstOrFail();

            $this->authorize('view', $query);

            $query = $this->manageUserProfilesFieldVisibility($request, $query);

            $query = $this->checkForIncludedRelations($request, $query);

            $query = $this->controlVisibleFields($request, $originalSelectFields, $query);

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
     * Update User Profile
     * 
     * Endpoint: PATCH /user-profiles/{id}
     *
     * Updates a user profile. Users can only update their own profiles,
     * while administrators can update any profile.
     *
     * @group User Profiles
     *
     * @urlParam id required The ID of the user profile to update. Example: 1
     *
     * @bodyParam display_name string The display name of the user (2-255 characters). Example: "John Developer"
     * @bodyParam public_email string|null The publicly visible email address. Example: "public@example.com"
     * @bodyParam website string|null User's website. Example: "https://example.com"
     * @bodyParam avatar_path string|null Path to the user's avatar. Example: "/storage/avatars/image.jpg"
     * @bodyParam is_public boolean Whether the profile is publicly visible. Example: true
     * @bodyParam location string|null The user's location. Example: "Berlin, Germany"
     * @bodyParam biography string|null User's biography or description. Example: "Full-stack developer with 5 years experience"
     * @bodyParam skills array|null Array of user skills. Example: ["PHP", "Laravel", "Vue.js"]
     * @bodyParam social_links object|null Social media links. Example: {"github": "https://github.com/username", "linkedin": "https://linkedin.com/in/username"}
     * @bodyParam contact_channels object|null Contact information, limited to "discord". Example: {"discord": "username#1234"}
     * @bodyParam auto_load_external_images boolean Whether to auto-load external images. Example: false
     * @bodyParam auto_load_external_videos boolean Whether to auto-load external videos. Example: false
     * @bodyParam auto_load_external_resources boolean Whether to auto-load external resources. Example: false
     * 
     * @bodyContent {
     *   "display_name": "John Developer",
     *   "public_email": "public@example.com",
     *   "is_public": true,
     *   "location": "Berlin, Germany",
     *   "skills": ["PHP", "Laravel", "Vue.js", "API Design"],
     *   "social_links": {
     *     "github": "https://github.com/johndev",
     *     "linkedin": "https://linkedin.com/in/john-developer"
     *   },
     *   "contact_channels": {
     *     "discord": "johndev#1234"
     *   },
     *   "auto_load_external_images": true,
     * }
     * 
     * @bodyContent application/json Only E-Mail-Update {
     *   "public_email": "new.email@example.com"
     * }
     *
     * @bodyContent application/json Skills Update {
     *   "skills": ["PHP", "Laravel", "Vue.js", "API Design", "TypeScript"]
     * }
     *
     * Example URL: /user-profiles/1
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "User Profile updated successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "display_name": "John Developer",
     *     "public_email": "public@example.com",
     *     "website": "https://example.com",
     *     "avatar_path": "/storage/avatars/johndev.jpg",
     *     "is_public": true,
     *     "location": "San Francisco, CA",
     *     "biography": "Full-stack developer with 5 years of experience",
     *     "skills": ["PHP", "Laravel", "JavaScript"],
     *     "social_links": {
     *       "github": "https://github.com/johndev",
     *       "linkedin": "https://linkedin.com/in/john-developer"
     *     },
     *     "contact_channels": {"discord": "johndev#1234"},
     *     "auto_load_external_images": false,
     *     "external_images_temp_until": null,
     *     "auto_load_external_videos": false,
     *     "external_videos_temp_until": null,
     *     "auto_load_external_resources": false,
     *     "external_resources_temp_until": null,
     *     "created_at": "2023-01-15T09:24:12.000000Z",
     *     "updated_at": "2023-05-20T14:35:47.000000Z"
     *   }
     * }
     *
     * @response status=404 scenario="Not Found" {
     *   "status": "error",
     *   "message": "User Profile with ID 999 does not exist",
     *   "code": 404,
     *   "errors": "PROFILE_NOT_FOUND"
     * }
     *
     * @response status=403 scenario="Unauthorized" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     * 
     * @response status=422 scenario="Validation Failed" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "display_name": [
     *       "DISPLAY_NAME_ALREADY_IN_USE"
     *     ],
     *     "social_links": [
     *       "SOCIAL_LINK_INVALID_FORMAT"
     *     ]
     *   }
     * }
     * 
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     *
     * @authenticated
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
     * Enable Temporary External Content
     * 
     * Endpoint: POST /user-profiles/{id}/enable-temporary-externals
     *
     * Temporarily enables loading of external content (images, videos, or resources) for a specific time period.
     * Setting hours to 0 will disable the temporary loading. Maximum allowed time is 72 hours.
     *
     * @group User Profiles
     *
     * @urlParam id required The ID of the user profile. Example: 1
     *
     * @bodyParam type string required The type of external content to enable. Must be one of: images, videos, resources. Example: images
     * @bodyParam hours integer required Number of hours to enable (0-72). Use 0 to disable. Example: 24
     * 
     * @bodyContent application/json Enable images for 24 hours {
     *   "type": "images",
     *   "hours": 24
     * }
     * 
     * @bodyContent application/json Disable temporary images {
     *   "type": "images",
     *   "hours": 0
     * }
     *
     * Example URL: /user-profiles/1/enable-temporary-externals
     *
     * @response status=200 scenario="Successfully Enabled" {
     *   "status": "success",
     *   "message": "Temporary images successfully activated for the next 24 hours.",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "display_name": "John Dev",
     *     "public_email": "john.public@example.com", 
     *     "website": "https://johndev.example.com",
     *     "avatar_path": "/storage/avatars/johndev.jpg",
     *     "is_public": true,
     *     "location": "San Francisco, CA",
     *     "biography": "Full-stack developer with 5 years of experience",
     *     "skills": ["PHP", "Laravel", "JavaScript"],
     *     "social_links": {
     *       "github": "https://github.com/johndev",
     *       "linkedin": "https://linkedin.com/in/john-developer"
     *     },
     *     "contact_channels": {"discord": "johndev#1234"},
     *     "auto_load_external_images": false,
     *     "external_images_temp_until": "2023-05-21T14:35:47.000000Z",         || This field was updated
     *     "auto_load_external_videos": false,
     *     "external_videos_temp_until": null,
     *     "auto_load_external_resources": false,
     *     "external_resources_temp_until": null,
     *     "created_at": "2023-01-15T09:24:12.000000Z",
     *     "updated_at": "2023-05-20T14:35:47.000000Z"
     *   }
     * }
     *
     * @response status=200 scenario="Successfully Disabled" {
     *   "status": "success",
     *   "message": "Temporary videos deactivated.",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "display_name": "John Dev",
     *     "public_email": "john.public@example.com",
     *     "website": "https://johndev.example.com",
     *     "avatar_path": "/storage/avatars/johndev.jpg",
     *     "is_public": true,
     *     "location": "San Francisco, CA",
     *     "biography": "Full-stack developer with 5 years of experience",
     *     "skills": ["PHP", "Laravel", "JavaScript"],
     *     "social_links": {
     *       "github": "https://github.com/johndev",
     *       "linkedin": "https://linkedin.com/in/john-developer"
     *     },
     *     "contact_channels": {"discord": "johndev#1234"},
     *     "auto_load_external_images": false,
     *     "external_images_temp_until": null,                                  || FIELD UPDATED: Set to null (disabled)
     *     "auto_load_external_videos": false,
     *     "external_videos_temp_until": null,
     *     "auto_load_external_resources": false,
     *     "external_resources_temp_until": null,
     *     "created_at": "2023-01-15T09:24:12.000000Z",
     *     "updated_at": "2023-05-20T14:35:47.000000Z"
     *   }
     * }
     *
     * @response status=404 scenario="Not Found" {
     *   "status": "error",
     *   "message": "User Profile with ID 999 does not exist",
     *   "code": 404,
     *   "errors": "PROFILE_NOT_FOUND"
     * }
     *
     * @response status=403 scenario="Unauthorized" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     * 
     * @response status=422 scenario="Validation Failed" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "type": [
     *       "TYPE_INVALID_OPTION"
     *     ],
     *     "hours": [
     *       "HOURS_CANNOT_EXCEED_72"
     *     ]
     *   }
     * }
     * 
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     *
     * @authenticated
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
