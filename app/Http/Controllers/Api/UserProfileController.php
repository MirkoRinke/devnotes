<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use App\Rules\AllowedContactChannels;
use App\Rules\AllowedSocialLinks;
use App\Rules\NotForbiddenName;
use App\Rules\SafeUrl;

use App\Models\UserProfile;

use App\Traits\ApiResponses;
use App\Traits\QueryBuilder;
use App\Traits\RelationLoader;
use App\Traits\ApiInclude;
use App\Traits\FieldManager;
use App\Traits\UserFollowerHelper;
use App\Traits\UserProfileHelper;

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
    use ApiResponses, QueryBuilder, AuthorizesRequests, RelationLoader, ApiInclude, FieldManager, UserFollowerHelper, UserProfileHelper;


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
     * 
     * @example | $this->getValidationRulesUpdate($userProfile)
     */
    public function getValidationRulesUpdate($userProfile): array {
        $validationRulesUpdate = [
            'display_name' => ['sometimes', 'required', 'unique:user_profiles,display_name,' . $userProfile->id, 'string', 'min:2', 'max:255', new NotForbiddenName()],
            'public_email' => 'sometimes|nullable|email|max:255',
            'website' => ['sometimes', 'nullable', 'string', 'max:255', new SafeUrl()],
            'is_public' => 'sometimes|required|boolean',
            'location' => 'sometimes|nullable|string|max:255',
            'biography' => 'sometimes|nullable|string',
            'skills' => 'sometimes|nullable|array',
            'social_links' => ['sometimes', 'nullable', 'array', new AllowedSocialLinks()],
            'contact_channels' => ['sometimes', 'nullable', 'array', new AllowedContactChannels()],
            'auto_load_external_images' => 'sometimes|required|boolean',
            'auto_load_external_videos' => 'sometimes|required|boolean',
            'auto_load_external_resources' => 'sometimes|required|boolean',
            'favorite_languages' => 'sometimes|array',
            'favorite_languages.*' =>  'sometimes|string',
        ];
        return $validationRulesUpdate;
    }


    /**
     * List User Profiles
     * 
     * Endpoint: GET /user-profiles
     *
     * Retrieves a list of user profiles. For regular users, only public profiles and their own profile are returned.
     * Administrators and moderators can see all profiles.
     *
     * The relation `favorite_languages` is always included.  
     * The relation `user` can be included via the `include` parameter.
     * You can use `user_fields` and `favorite_languages_fields` to specify which fields should be returned for these relations.
     *
     * Example: `/user-profiles?include=user&user_fields=id,display_name&favorite_languages_fields=name`
     *
     * @group User Profiles
     *
     * @queryParam select   See [ApiSelectable](#apiselectable) for field selection details. Example: select=id,display_name,website
     * @see \App\Traits\ApiSelectable::select()
     * 
     * @queryParam sort     See [ApiSorting](#apisorting) for sorting details. Example: sort=-created_at
     * @see \App\Traits\ApiSorting::sort()
     * 
     * @queryParam filter   See [ApiFiltering](#apifiltering) for filtering details. Example: filter[is_public]=true
     * @see \App\Traits\ApiFiltering::filter()
     * 
     * @queryParam include  See [ApiInclude](#apiinclude) for relation inclusion details (only `user` is supported). Example: include=user
     * @see \App\Traits\ApiInclude::getRelationKeyFields()
     * 
     * @queryParam *_fields string See [ApiInclude](#apiinclude). When including a relation or for always-included relations (favorite_languages), specify fields to return. Example: favorite_languages_fields=name
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest() for dynamic includes
     *
     * @queryParam page     Pagination, see [ApiPagination](#apipagination). Example: page=1
     * @see \App\Traits\ApiPagination::paginate()
     * 
     * @queryParam per_page Pagination, see [ApiPagination](#apipagination). Example: per_page=15
     * @see \App\Traits\ApiPagination::paginate()
     * 
     * @queryParam setLimit Disables pagination and limits the number of results. See [ApiLimit](#apilimit). Example: setLimit=10
     * @see \App\Traits\ApiLimit::setLimit()
     *
     * Example URL: /user-profiles
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "User Profiles retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 1,
     *       "display_name": "Admin",
     *       "public_email": "contact@mirkorinke.dev",
     *       "website": "https://mirkorinke.dev/",
     *       "is_public": true,
     *       "location": "Hildesheim, Germany",
     *       "biography": "...",
     *       "skills": [
     *         "TypeScript",
     *         "Angular",
     *         "PHP",
     *         "Laravel",
     *         "MySQL"
     *       ],
     *       "social_links": {
     *         "github": "https://github.com/MirkoRinke",
     *         "linkedin": "https://linkedin.com/in/mirkorinke"
     *       },
     *       "contact_channels": {
     *         "discord": "gallifrey87"
     *       },
     *       "auto_load_external_images": true,
     *       "external_images_temp_until": null,
     *       "auto_load_external_videos": true,
     *       "external_videos_temp_until": null,
     *       "auto_load_external_resources": true,
     *       "external_resources_temp_until": null,
     *       "reports_count": 0,                                        || Admin and Moderator only
     *       "created_at": "2025-07-09T17:26:42.000000Z",
     *       "updated_at": "2025-07-12T20:54:32.000000Z",
     *       "favorite_languages": [
     *         { "id": 4, "name": "JavaScript" },
     *         { "id": 5, "name": "TypeScript" },
     *         { "id": 12, "name": "Go" }
     *       ]
     *     }
     *   ]
     * }
     *
     * Example URL: /user-profiles/?include=user
     *
     * @response status=200 scenario="Success (with user include)" {
     *   "status": "success",
     *   "message": "User Profiles retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       ... // Same profile fields as above
     *       "user": {
     *         "id": 1,
     *         "display_name": "Admin",
     *         "role": "admin",
     *         "created_at": "2025-07-09T17:26:42.000000Z",
     *         "updated_at": "2025-07-09T17:26:42.000000Z",
     *         "is_banned": null,                                       || Admin and Moderator only
     *         "was_ever_banned": false,                                || Admin and Moderator only
     *         "moderation_info": [],                                   || Admin and Moderator only
     *         "is_following": false                                    || Virtual field, true if the authenticated user follows this user
     *       },
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

            $query = $this->applyProfileAccessFilters($request);

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

            $query = $this->isFollowing($request, $query);

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
     * The relation `favorite_languages` is always included.  
     * The relation `user` can be included via the `include` parameter.
     * You can use `user_fields` and `favorite_languages_fields` to specify which fields should be returned for these relations.
     *
     * Example: `/user-profiles/1?include=user&user_fields=id,display_name&favorite_languages_fields=name`
     *
     * @group User Profiles
     *
     * @urlParam id required The ID of the user profile. Example: 1
     *
     * @queryParam select   See [ApiSelectable](#apiselectable) for field selection details. Example: select=id,display_name,website
     * @see \App\Traits\ApiSelectable::select()
     * 
     * @queryParam include  See [ApiInclude](#apiinclude) for relation inclusion details (only `user` is supported). Example: include=user
     * @see \App\Traits\ApiInclude::getRelationKeyFields()
     * 
     * @queryParam *_fields string See [ApiInclude](#apiinclude). When including a relation or for always-included relations (favorite_languages), specify fields to return. Example: favorite_languages_fields=name
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest() for dynamic includes
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
     *     "display_name": "Admin",
     *     "public_email": "contact@mirkorinke.dev",
     *     "website": "https://mirkorinke.dev/",
     *     "is_public": true,
     *     "location": "Hildesheim, Germany",
     *     "biography": "...",
     *     "skills": [
     *       "TypeScript",
     *       "Angular",
     *       "PHP",
     *       "Laravel",
     *       "MySQL"
     *     ],
     *     "social_links": {
     *       "github": "https://github.com/MirkoRinke",
     *       "linkedin": "https://linkedin.com/in/mirkorinke"
     *     },
     *     "contact_channels": {
     *       "discord": "gallifrey87"
     *     },
     *     "auto_load_external_images": true,
     *     "external_images_temp_until": null,
     *     "auto_load_external_videos": true,
     *     "external_videos_temp_until": null,
     *     "auto_load_external_resources": true,
     *     "external_resources_temp_until": null,
     *     "reports_count": 0,                                        || Admin and Moderator only
     *     "created_at": "2025-07-09T17:26:42.000000Z",
     *     "updated_at": "2025-07-12T20:54:32.000000Z",
     *     "favorite_languages": [
     *       { "id": 4, "name": "JavaScript" },
     *       { "id": 5, "name": "TypeScript" },
     *       { "id": 12, "name": "Go" }
     *     ]
     *   }
     * }
     *
     * Example URL: /user-profiles/1?include=user
     *
     * @response status=200 scenario="Success (with user include)" {
     *   "status": "success",
     *   "message": "User Profile retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     ... // Same profile fields as above
     *     "user": {
     *       "id": 1,
     *       "display_name": "Admin",
     *       "role": "admin",
     *       "created_at": "2025-07-09T17:26:42.000000Z",
     *       "updated_at": "2025-07-09T17:26:42.000000Z",
     *       "is_banned": null,                                       || Admin and Moderator only
     *       "was_ever_banned": false,                                || Admin and Moderator only
     *       "moderation_info": [],                                   || Admin and Moderator only
     *       "is_following": false                                    || Virtual field, true if the authenticated user follows this user
     *     }
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

            /**
             * Need this because the buildQuerySelect method returns only the query object
             */
            $query = $query->firstOrFail();

            $this->authorize('view', $query);

            $query = $this->manageUserProfilesFieldVisibility($request, $query);

            $query = $this->checkForIncludedRelations($request, $query);

            $query = $this->controlVisibleFields($request, $originalSelectFields, $query);

            $query = $this->isFollowing($request, $query);

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
     * All fields are optional; at least one field must be provided.
     * The relation `favorite_languages` is always included in the response.
     *
     * @group User Profiles
     *
     * @urlParam id required The ID of the user profile to update. Example: 1
     *
     * @bodyParam display_name string The display name of the user (2-255 characters). Example: "Admin"
     * @bodyParam public_email string|null The publicly visible email address. Example: "contact@mirkorinke.dev"
     * @bodyParam website string|null User's website. Example: "https://mirkorinke.dev/"
     * @bodyParam is_public boolean Whether the profile is publicly visible. Example: true
     * @bodyParam location string|null The user's location. Example: "Hildesheim, Germany"
     * @bodyParam biography string|null User's biography or description. Example: "..."
     * @bodyParam skills array|null Array of user skills. Example: ["TypeScript", "Angular", "PHP", "Laravel", "MySQL"]
     * @bodyParam social_links object|null Social media links. Example: {"github": "https://github.com/MirkoRinke", "linkedin": "https://linkedin.com/in/mirkorinke"}
     * @bodyParam contact_channels object|null Contact information, limited to "discord". Example: {"discord": "gallifrey87"}
     * @bodyParam auto_load_external_images boolean Whether to auto-load external images. Example: true
     * @bodyParam auto_load_external_videos boolean Whether to auto-load external videos. Example: true
     * @bodyParam auto_load_external_resources boolean Whether to auto-load external resources. Example: true
     * @bodyParam favorite_languages array|null Array of language names. Example: ["JavaScript", "TypeScript", "Go"]
     *
     * @bodyContent application/json Full Update {
     *   "display_name": "Admin",
     *   "public_email": "contact@mirkorinke.dev",
     *   "website": "https://mirkorinke.dev/",    
     *   "is_public": true,
     *   "location": "Hildesheim, Germany",
     *   "biography": "...",
     *   "skills": ["TypeScript", "Angular", "PHP", "Laravel", "MySQL"],
     *   "social_links": {
     *     "github": "https://github.com/MirkoRinke",
     *     "linkedin": "https://linkedin.com/in/mirkorinke"
     *   },
     *   "contact_channels": {
     *     "discord": "gallifrey87"
     *   },
     *   "auto_load_external_images": true,
     *   "auto_load_external_videos": true,
     *   "auto_load_external_resources": true,
     *   "favorite_languages": ["JavaScript", "TypeScript", "Go"]
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
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "User Profile updated successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "display_name": "Admin",
     *     "public_email": "contact@mirkorinke.dev",
     *     "website": "https://mirkorinke.dev/",
     *     "is_public": true,
     *     "location": "Hildesheim, Germany",
     *     "biography": "...",
     *     "skills": [
     *       "TypeScript",
     *       "Angular",
     *       "PHP",
     *       "Laravel",
     *       "MySQL"
     *     ],
     *     "social_links": {
     *       "github": "https://github.com/MirkoRinke",
     *       "linkedin": "https://linkedin.com/in/mirkorinke"
     *     },
     *     "contact_channels": {
     *       "discord": "gallifrey87"
     *     },
     *     "auto_load_external_images": true,
     *     "external_images_temp_until": null,
     *     "auto_load_external_videos": true,
     *     "external_videos_temp_until": null,
     *     "auto_load_external_resources": true,
     *     "external_resources_temp_until": null,
     *     "created_at": "2025-07-09T17:26:42.000000Z",
     *     "updated_at": "2025-07-12T20:54:32.000000Z",
     *     "favorite_languages": [
     *       { "id": 4, "name": "JavaScript" },
     *       { "id": 5, "name": "TypeScript" },
     *       { "id": 12, "name": "Go" }
     *     ]
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
     * @response status=422 scenario="No Fields Provided" {
     *   "status": "error",
     *   "message": "At least one field must be provided for update",
     *   "code": 422,
     *   "errors": "NO_FIELDS_PROVIDED"
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

            if (empty($validatedData)) {
                return $this->errorResponse('At least one field must be provided for update', 'NO_FIELDS_PROVIDED', 422);
            }

            if (isset($validatedData['favorite_languages']) && is_array($validatedData['favorite_languages'])) {
                $result = $this->syncFavoriteLanguages($userProfile, $validatedData['favorite_languages']);
                unset($validatedData['favorite_languages']);
                if ($result instanceof JsonResponse) {
                    return $result;
                }
            }

            $userProfile = DB::transaction(function () use ($userProfile, $validatedData) {

                $userProfile->update($validatedData);
                $this->userRelationService->updateProfileDisplayName($userProfile);

                $userProfile->load(['favoriteLanguages:id,name']);

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
    public function destroy(): JsonResponse {
        return $this->errorResponse('Profiles are automatically managed and cannot be deleted manually', 'PROFILE_DELETE_DISABLED', 405);
    }


    /**
     * Enable Temporary External Content
     * 
     * Endpoint: POST /user-profiles/{id}/enable-temporary-externals
     *
     * Temporarily enables loading of external content (images, videos, or resources) for a specific time period.
     * Setting hours to 0 will disable the temporary loading. Maximum allowed time is 72 hours.
     * Only the owner of the profile can use this endpoint for the own profile.
     *
     * @group User Profiles
     *
     * @urlParam id required The ID of the user profile. Example: 1
     *
     * @bodyParam type string required The type of external content to enable. Must be one of: images, videos, resources. Example: images
     * @bodyParam hours integer required Number of hours to enable (0-72). Use 0 to disable. Example: 24
     * 
     * @bodyContent application/json Enable resources for 2 hours {
     *   "type": "resources",
     *   "hours": 2
     * }
     *
     * @response status=200 scenario="Successfully Enabled" {
     *   "status": "success",
     *   "message": "Temporary resources successfully activated for the next 2 hours.",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "display_name": "Admin",
     *     "public_email": "contact@mirkorinke.dev",
     *     "website": "https://mirkorinke.dev/",
     *     "is_public": true,
     *     "location": "Hildesheim, Germany",
     *     "biography": "...",
     *     "skills": [
     *       "TypeScript",
     *       "Angular",
     *       "PHP",
     *       "Laravel",
     *       "MySQL"
     *     ],
     *     "social_links": {
     *       "github": "https://github.com/MirkoRinke",
     *       "linkedin": "https://linkedin.com/in/mirkorinke"
     *     },
     *     "contact_channels": {
     *       "discord": "gallifrey87"
     *     },
     *     "auto_load_external_images": true,
     *     "external_images_temp_until": null,
     *     "auto_load_external_videos": true,
     *     "external_videos_temp_until": null,
     *     "auto_load_external_resources": true,
     *     "external_resources_temp_until": "2025-07-13T00:03:31.000000Z",
     *     "created_at": "2025-07-09T17:26:42.000000Z",
     *     "updated_at": "2025-07-12T22:03:31.000000Z"
     *   }
     * }
     *
     * @bodyContent application/json Disable temporary images {
     *   "type": "images",
     *   "hours": 0
     * }
     * 
     * @response status=200 scenario="Successfully Disabled" {
     *   "status": "success",
     *   "message": "Temporary images deactivated.",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     ... // Same profile fields as above
     *     "external_images_temp_until": null
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
