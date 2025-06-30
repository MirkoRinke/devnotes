<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use App\Models\User;

use App\Rules\NotForbiddenName;

use App\Traits\ApiResponses;
use App\Traits\QueryBuilder;
use App\Traits\ApiInclude;
use App\Traits\RelationLoader;
use App\Traits\FieldManager;

use App\Services\ModerationService;
use App\Services\UserRelationService;
use App\Services\GuestAccountService;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Illuminate\Auth\Access\AuthorizationException;

class UserController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, QueryBuilder, AuthorizesRequests, ApiInclude, RelationLoader, FieldManager;

    /**
     *  The services used in the controller
     */
    protected $moderationService;
    protected $userRelationService;
    protected $guestAccountService;

    /**
     * Constructor to initialize the services
     */
    public function __construct(
        ModerationService $moderationService,
        UserRelationService $userRelationService,
        GuestAccountService $guestAccountService
    ) {
        $this->moderationService = $moderationService;
        $this->userRelationService = $userRelationService;
        $this->guestAccountService = $guestAccountService;
    }

    /**
     * The validation rules for the user data
     * 
     * @param User $user
     * @return array
     * 
     * @example | $this->getValidationRulesUpdate($user)
     */
    public function getValidationRulesUpdate($user): array {
        $validationRules = [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255', new NotForbiddenName()],
            'email' => 'sometimes|required|string|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|required|string|min:8|confirmed',
        ];
        return $validationRules;
    }

    /**
     * Setup the query for User
     * 
     * @param Request $request
     * @param mixed $query 
     * @param string $methods (string) The method to call for building the query
     * @return mixed
     * 
     * @example | $query = $this->setupUserQuery($request, $query, 'buildQuery');
     */
    protected function setupUserQuery(Request $request, $query, $methods) {
        $this->modifyRequestSelect($request, ['id']);

        $query = $this->loadProfileRelation($request, $query, 'id');

        $query = $this->$methods($request, $query, 'user');

        return $query;
    }


    /**
     * List all users
     * 
     * Endpoint: GET /users
     *
     * Retrieves a list of users with support for filtering, sorting, field selection, relation inclusion, and pagination.
     * Only administrators can access this endpoint.
     *
     * @group User Management
     *
     * @queryParam select   See [ApiSelectable](#apiselectable) for field selection details.
     * @see \App\Traits\ApiSelectable::select()
     * 
     * @queryParam sort     See [ApiSorting](#apisorting) for sorting details.
     * @see \App\Traits\ApiSorting::sort()
     * 
     * @queryParam filter   See [ApiFiltering](#apifiltering) for filtering details.
     * @see \App\Traits\ApiFiltering::filter()
     * 
     * @queryParam include  See [ApiInclude](#apiinclude) for relation inclusion details (e.g. profile).
     * @see \App\Traits\ApiInclude::getRelationKeyFields()
     * 
     * @queryParam *_fields string See [ApiInclude](#apiinclude). When including a relation, specify fields to return. Example: profile_fields=id,display_name
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest()
     *
     * @queryParam page     Pagination, see [ApiPagination](#apipagination).
     * @see \App\Traits\ApiPagination::paginate()
     * 
     * @queryParam per_page Pagination, see [ApiPagination](#apipagination).
     * @see \App\Traits\ApiPagination::paginate()
     * 
     * @queryParam setLimit Disables pagination and limits the number of results. See [ApiLimit](#apilimit).
     * @see \App\Traits\ApiLimit::setLimit()
     *
     * Example URL: /users
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Users retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Admin",
     *       "email_verified_at": "2025-06-30T01:01:51.000000Z",
     *       "email": "contact@mirkorinke.dev",
     *       "display_name": "Admin",
     *       "role": "admin",
     *       "is_banned": null,                             || Admin and Moderator only
     *       "was_ever_banned": false,                      || Admin and Moderator only
     *       "moderation_info": [],                         || Admin and Moderator only
     *       "created_at": "2025-06-30T01:01:51.000000Z",
     *       "updated_at": "2025-06-30T01:01:51.000000Z"
     *     }
     *   ]
     * }
     *
     * Example URL: /users/?include=profile
     *
     * @response status=200 scenario="Success with profile include" {
     *   "status": "success",
     *   "message": "Users retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *     ..... // Same user data as above
     *       "profile": {
     *         "id": 1,
     *         "user_id": 1,
     *         "display_name": "Admin",
     *         "public_email": "contact@mirkorinke.dev",
     *         "website": "https://mirkorinke.dev/",
     *         "avatar_path": null,
     *         "is_public": true,
     *         "location": "Hildesheim, Germany",
     *         "biography": "...",
     *         "skills": [
     *           "TypeScript",
     *           "Angular",
     *           "PHP",
     *           "Laravel",
     *           "MySQL"
     *         ],
     *         "social_links": {
     *           "github": "https://github.com/MirkoRinke",
     *           "linkedin": "https://linkedin.com/in/mirkorinke"
     *         },
     *         "contact_channels": {
     *           "discord": "gallifrey87"
     *         },
     *         "auto_load_external_images": true,
     *         "external_images_temp_until": null,
     *         "auto_load_external_videos": true,
     *         "external_videos_temp_until": null,
     *         "auto_load_external_resources": true,
     *         "external_resources_temp_until": null,
     *         "reports_count": 0,                          || Admin and Moderator only
     *         "created_at": "2025-06-30T01:01:51.000000Z",
     *         "updated_at": "2025-06-30T14:36:05.000000Z"
     *       }
     *     }
     *   ]
     * }
     *
     * @response status=200 scenario="Empty database" {
     *   "status": "success",
     *   "message": "No users exist in the database",
     *   "code": 200,
     *   "data": []
     * }
     *
     * @response status=200 scenario="No users found" {
     *   "status": "success",
     *   "message": "No users found with the given filters",
     *   "code": 200,
     *   "count": 0,
     *   "data": []
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
     * Note:
     * - Only administrators can access this endpoint.
     * 
     * @authenticated
     */
    public function index(Request $request): JsonResponse {
        try {
            $this->authorize('viewAny', User::class);

            if (User::count() === 0) {
                return $this->successResponse([], 'No users exist in the database', 200);
            }

            $query = User::query();

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupUserQuery($request, $query, 'buildQuery');
            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No users found with the given filters', 200);
            }

            $query = $this->manageUsersFieldVisibility($request, $query);

            $query = $this->checkForIncludedRelations($request, $query);

            $query = $this->controlVisibleFields($request, $originalSelectFields, $query);

            return $this->successResponse($query, 'Users retrieved successfully', 200);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Get a specific user
     * 
     * Endpoint: GET /users/{id}
     *
     * Retrieves detailed information about a single user by their ID.
     * Only administrators can access any user. Each user can access their own data.
     * If the requesting user is an administrator or moderator, additional information like ban status and moderation history will be included in the response.
     *
     * @group User Management
     * 
     * @urlParam id required The ID of the user to retrieve. Example: 123
     *
     * @queryParam select   See [ApiSelectable](#apiselectable) for field selection details.
     * @see \App\Traits\ApiSelectable::select()
     * 
     * @queryParam include  See [ApiInclude](#apiinclude) for relation inclusion details (e.g. profile).
     * @see \App\Traits\ApiInclude::getRelationKeyFields()
     * 
     * @queryParam profile_fields string See [ApiInclude](#apiinclude). When including profile relation, specify fields to return. Example: profile_fields=id,user_id,display_name,public_email
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest()
     * 
     * Example URL: /users/123
     *
     * @response status=200 scenario="Success without profile include" {
     *   "status": "success",
     *   "message": "User retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 123,
     *     "name": "Francisco Pfeffer",
     *     "email_verified_at": "2025-06-30T01:01:54.000000Z",
     *     "email": "trenton.mckenzie@example.com",
     *     "display_name": "madeline.jerde",
     *     "role": "user",
     *     "is_banned": null,                                   || Admin and Moderator only
     *     "was_ever_banned": false,                            || Admin and Moderator only
     *     "moderation_info": [],                               || Admin and Moderator only
     *     "created_at": "2025-06-30T01:01:54.000000Z",
     *     "updated_at": "2025-06-30T01:01:54.000000Z"
     *   }
     * }
     *
     * Example URL: /users/123/?include=profile
     *
     * @response status=200 scenario="Success with profile include" {
     *   "status": "success",
     *   "message": "User retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     ..... // Same user data as above
     *     "profile": {
     *       "id": 123,
     *       "user_id": 123,
     *       "display_name": "madeline.jerde",
     *       "public_email": "madeline.jerde@example.com",
     *       "website": null,
     *       "avatar_path": null,
     *       "is_public": true,
     *       "location": "Berlin, Germany",
     *       "biography": null,
     *       "skills": "PHP, Laravel, MySQL",
     *       "social_links": null,
     *         "contact_channels": {
     *           "discord": "madeline.jerde#1234"
     *         },
     *       "auto_load_external_images": false,
     *       "external_images_temp_until": null,
     *       "auto_load_external_videos": false,
     *       "external_videos_temp_until": null,
     *       "auto_load_external_resources": false,
     *       "external_resources_temp_until": null,
     *       "reports_count": 0,                                || Admin and Moderator only
     *       "created_at": "2025-06-30T01:01:54.000000Z",
     *       "updated_at": "2025-06-30T01:01:54.000000Z"
     *     }
     *   }
     * }
     *
     * @response status=404 scenario="User not found" {
     *   "status": "error",
     *   "message": "User with ID 999 does not exist",
     *   "code": 404,
     *   "errors": "USER_NOT_FOUND"
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
     * Note:
     * - Only administrators can access any user. Each user can access their own data.
     * 
     * @authenticated
     */
    public function show(string $id, Request $request): JsonResponse {
        try {
            $query = User::query()->where('id', $id);

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupUserQuery($request, $query, 'buildQuerySelect');
            if ($query instanceof JsonResponse) {
                return $query;
            }

            /**
             * Need this because the select method returns only the query object
             */
            $user = $query->firstOrFail();

            $user = $this->manageUsersFieldVisibility($request, $user);

            $user = $this->checkForIncludedRelations($request, $user);

            $user = $this->controlVisibleFields($request, $originalSelectFields, $user);

            $this->authorize('view', $user);

            return $this->successResponse($user, 'User retrieved successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("User with ID $id does not exist", 'USER_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Update a user
     * 
     * Endpoint: PATCH /users/{id}
     *
     * Updates the specified user's information. Users can only update their own data (name, email, password),
     * while administrators and moderators can update any user.
     * If the password is changed, all other tokens except the current one will be deleted (logout on other devices).
     * Fields like is_banned, was_ever_banned, and moderation_info are only visible to admins and moderators.
     *
     * @group User Management
     *
     * @urlParam id required The ID of the user to update. Example: 22
     *
     * @bodyParam name string Name of the user (2-255 characters). Example: "Max Mustermann"
     * @bodyParam email string Email address (must be unique). Example: "max@example.com"
     * @bodyParam password string Password (min 8 characters). Example: "sicheresPasswort1234"
     * @bodyParam password_confirmation string Password confirmation (must match password). Example: "sicheresPasswort1234"
     *
     * @bodyContent {
     *   "name": "Max Mustermann99",                        || optional, string, min:2, max:255
     *   "email": "max@example99.com",                      || optional, string, email, unique
     *   "password": "sicheresPasswort1234",                || optional, string, min:8, confirmed
     *   "password_confirmation": "sicheresPasswort1234"    || required if password is set, string, must match password
     * }
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "User update successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 22,
     *     "name": "Max Mustermann99",
     *     "email_verified_at": "2025-06-30T19:09:26.000000Z",
     *     "email": "max@example99.com",
     *     "display_name": "Maxi22",
     *     "role": "user",
     *     "is_banned": null,                                       || Admin and Moderator only
     *     "was_ever_banned": false,                                || Admin and Moderator only
     *     "moderation_info": [],                                   || Admin and Moderator only
     *     "created_at": "2025-06-30T19:09:26.000000Z",
     *     "updated_at": "2025-06-30T19:21:03.000000Z"
     *   }
     * }
     *
     * @response status=404 scenario="User not found" {
     *   "status": "error",
     *   "message": "User with ID 999 does not exist",
     *   "code": 404,
     *   "errors": "USER_NOT_FOUND"
     * }
     *
     * @response status=403 scenario="Unauthorized" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     *
     * @response status=422 scenario="Validation error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "name": ["The name field is required."],
     *     "email": ["The email has already been taken."]
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
     * Note:
     * - Users can only update their own data. Administrators and moderators can update any user.
     * - Only name, email, and password can be updated.
     * - If the password is changed, all other tokens except the current one will be deleted (logout on other devices).
     * - Fields like is_banned, was_ever_banned, and moderation_info are only visible to admins and moderators.
     * 
     * @authenticated
     */
    public function update(Request $request, string $id): JsonResponse {
        try {
            $user = User::findOrFail($id);

            $this->authorize('update', $user);

            $validatedData = $request->validate(
                $this->getValidationRulesUpdate($user),
                $this->getValidationMessages('User')
            );

            $user = DB::transaction(function () use ($user, $validatedData, $request) {
                $nameChanged = isset($validatedData['name']) && $validatedData['name'] !== $user->name;

                $user->fill([
                    'name' => $validatedData['name'] ?? $user->name,
                    'email' => $validatedData['email'] ?? $user->email,
                ]);

                if (isset($validatedData['password']) && $user === $request->user()) {
                    $user->password = bcrypt($validatedData['password']);

                    // If the password is changed, delete all other tokens except the current one
                    $tokens = $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->get();
                    $tokens->each->delete();
                }

                $user->save();

                if ($nameChanged) {
                    $this->userRelationService->checkUsername($user);
                }
                return $user;
            });

            $user = $this->manageUsersFieldVisibility($request, $user);

            return $this->successResponse($user, 'User update successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("User with ID $id does not exist", 'USER_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Delete a user
     * 
     * Endpoint: DELETE /users/{id}
     *
     * Permanently deletes a user account. For regular users, all posts and comments will be transferred to the system user ("Deleted User"), and all reports, likes, and favorites will be deleted.
     * Guest accounts receive special handling and will be reset (recreated).
     *
     * Only administrators can delete other users' accounts. Regular users can only delete their own account.  
     * It is not possible to delete admin, moderator, or system accounts. Guest accounts cannot delete themselves.
     *
     * @group User Management
     *
     * @urlParam id required The ID of the user to delete. Example: 5
     *
     * @response status=200 scenario="Regular user deleted" {
     *   "status": "success",
     *   "message": "User deleted successfully",
     *   "code": 200,
     *   "data": null
     * }
     *
     * @response status=200 scenario="Guest account deleted" {
     *   "status": "success",
     *   "message": "Guest account reset successfully",
     *   "code": 200,
     *   "data": null
     * }
     *
     * @response status=404 scenario="User not found" {
     *   "status": "error",
     *   "message": "User with ID 999 does not exist",
     *   "code": 404,
     *   "errors": "USER_NOT_FOUND"
     * }
     *
     * @response status=403 scenario="Unauthorized" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     *
     * @response status=500 scenario="Guest reset failed" {
     *   "status": "error",
     *   "message": "Failed to reset guest account",
     *   "code": 500,
     *   "errors": "GUEST_RESET_FAILED"
     * }
     *
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     *
     * Note:
     * - There is no soft-delete; the user is permanently removed.
     * 
     * @authenticated
     */
    public function destroy(string $id): JsonResponse {
        try {
            $user = User::findOrFail($id);

            $this->authorize('delete', $user);

            /**
             *  Check if the user is a guest account
             *  If so, use the special handling for guest account deletion and recreation
             */
            if ($user->account_purpose === 'guest') {
                $success = $this->guestAccountService->resetGuestAccount($user);
                if ($success) {
                    return $this->successResponse(null, 'Guest account reset successfully', 200);
                } else {
                    return $this->errorResponse('Failed to reset guest account', 'GUEST_RESET_FAILED', 500);
                }
            }

            DB::transaction(function () use ($user) {
                // Transfer all posts and comments to the system user (ID 3)
                $this->userRelationService->transferPosts($user);
                $this->userRelationService->transferComments($user);

                // Delete all reports and likes associated with the user
                $this->userRelationService->deleteReports($user);
                $this->userRelationService->deleteLikes($user);

                // Delete all tokens associated with the user
                $user->tokens()->delete();

                /**
                 * Note: Favorites are automatically deleted through 
                 * database foreign key constraints (onDelete('cascade')) 
                 * and don't require explicit deletion here.
                 */

                $user->delete();
            });

            return $this->successResponse(null, 'User deleted successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("User with ID $id does not exist", 'USER_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Ban a user
     * 
     * Endpoint: POST /users/{id}/ban
     *
     * Bans a user for a specified number of days. Setting a very large number of days (e.g., 99999) is effectively a permanent ban.
     * Only administrators can ban users. Admin accounts cannot be banned.
     * Banned users are immediately logged out on all devices and cannot log in until the ban expires.
     * Each ban action is recorded in the moderation_info array.
     *
     * @group User Management
     *
     * @urlParam id required The ID of the user to ban. Example: 138
     *
     * @bodyParam moderation_reason string required Reason for banning the user (max 255 characters). Example: Violation of terms of service
     * @bodyParam days integer required Number of days to ban the user (1-99999). Use a large value like 99999 for a permanent ban. Example: 7
     * 
     * @bodyContent scenario="Temporary ban (7 days)" {
     *   "moderation_reason": "Repeated violation of community guidelines",
     *   "days": 7
     * }
     * 
     * @bodyContent scenario="Permanent ban" {
     *   "moderation_reason": "Severe violation of terms of service",
     *   "days": 99999
     * }
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "User banned successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 138,
     *     "name": "Minnie Abernathy",
     *     "is_banned": "2025-07-05T20:24:28.000000Z",
     *     "was_ever_banned": true,
     *     "moderation_info": [
     *       {
     *         "user_id": 1,
     *         "username": "Admin",
     *         "role": "admin",
     *         "timestamp": "2025-06-30T22:24:28+02:00",
     *         "reason": "Violation of terms of service",
     *         "action": "ban"
     *       }
     *     ]
     *   }
     * }
     *
     * @response status=409 scenario="Already banned" {
     *   "status": "error",
     *   "message": "User is already banned",
     *   "code": 409,
     *   "errors": "USER_ALREADY_BANNED"
     * }
     *
     * @response status=404 scenario="User not found" {
     *   "status": "error",
     *   "message": "User with ID 999 does not exist",
     *   "code": 404,
     *   "errors": "USER_NOT_FOUND"
     * }
     *
     * @response status=403 scenario="Unauthorized" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     *
     * @response status=422 scenario="Validation error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "moderation_reason": ["The moderation reason field is required."],
     *     "days": ["The days must be at least 1."]
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
     * Note:
     * - Only administrators can ban users. Admin accounts cannot be banned.
     * - Banned users are immediately logged out on all devices.
     * - Each ban is recorded in the moderation_info array.
     * - Use a large value for days (e.g., 99999) for a permanent ban.
     * 
     * @authenticated
     */
    public function banUser(string $id, Request $request): JsonResponse {
        try {
            $user = User::findOrFail($id);

            if ($user->is_banned && now()->lt($user->is_banned)) {
                return $this->errorResponse('User is already banned', 'USER_ALREADY_BANNED', 409);
            }

            $this->authorize('banUser', $user);

            $validatedData = $request->validate(
                [
                    'moderation_reason' => 'required|string|max:255',
                    'days' => 'required|integer|min:1|max:99999'
                ],
                $this->getValidationMessages('User')
            );

            $days = $validatedData['days'];

            /**
             * Remove the 'days' key from the validated data
             * this was only required for setting the ban duration
             */
            unset($validatedData['days']);

            $bannedTime = now()->addDays($days);

            $user = DB::transaction(function () use ($user, $bannedTime, $validatedData, $request) {
                $user = $this->moderationService->handleModerationUpdate(
                    $user,
                    $validatedData,
                    $request,
                    [],
                    'banUser'
                );

                $user->is_banned = $bannedTime;
                $user->was_ever_banned = true;

                // Delete all tokens to force logout on all devices
                $user->tokens()->delete();

                $user->save();

                return $user;
            });

            $bannedUserInfo = [
                'id' => $user->id,
                'name' => $user->name,
                'is_banned' => $user->is_banned,
                'was_ever_banned' => $user->was_ever_banned,
                'moderation_info' => $user->moderation_info,
            ];

            return $this->successResponse($bannedUserInfo, 'User banned successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("User with ID $id does not exist", 'USER_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Unban a user
     * 
     * Endpoint: POST /users/{id}/unban
     *
     * Removes an active ban from a user. Only administrators can unban users.
     * This will allow the user to log in again immediately. Each unban action is recorded in the moderation_info array.
     *
     * @group User Management
     *
     * @urlParam id required The ID of the user to unban. Example: 138
     *
     * @bodyParam moderation_reason string required Reason for unbanning the user (max 255 characters). Example: User appealed successfully
     * 
     * @bodyContent {
     *   "moderation_reason": "User appealed successfully"
     * }
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "User unbanned successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 138,
     *     "name": "Minnie Abernathy",
     *     "is_banned": null,
     *     "was_ever_banned": true,
     *     "moderation_info": [
     *       {
     *         "user_id": 1,
     *         "username": "Admin",
     *         "role": "admin",
     *         "timestamp": "2025-06-30T22:32:06+02:00",
     *         "reason": "User appealed successfully",
     *         "action": "unban"
     *       },
     *       {
     *         "user_id": 1,
     *         "username": "Admin",
     *         "role": "admin",
     *         "timestamp": "2025-06-30T22:24:28+02:00",
     *         "reason": "Violation of terms of service",
     *         "action": "ban"
     *       }
     *     ]
     *   }
     * }
     *
     * @response status=409 scenario="Not banned" {
     *   "status": "error",
     *   "message": "User is not banned",
     *   "code": 409,
     *   "errors": "USER_NOT_BANNED"
     * }
     *
     * @response status=404 scenario="User not found" {
     *   "status": "error",
     *   "message": "User with ID 999 does not exist",
     *   "code": 404,
     *   "errors": "USER_NOT_FOUND"
     * }
     *
     * @response status=403 scenario="Unauthorized" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     *
     * @response status=422 scenario="Validation error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "moderation_reason": ["The moderation reason field is required."]
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
     * Note:
     * - Only administrators can unban users.
     * - Each unban is recorded in the moderation_info array.
     * - The user can log in again immediately after being unbanned.
     * 
     * @authenticated
     */
    public function unbanUser(string $id, Request $request): JsonResponse {
        try {
            $user = User::findOrFail($id);

            if (!$user->is_banned || now()->gt($user->is_banned)) {
                return $this->errorResponse('User is not banned', 'USER_NOT_BANNED', 409);
            }

            $this->authorize('unbanUser', $user);

            $validatedData = $request->validate([
                'moderation_reason' => 'required|string|max:255',
            ]);

            $user = $this->moderationService->handleModerationUpdate(
                $user,
                $validatedData,
                $request,
                [],
                'unbanUser'
            );

            $user->is_banned = null;

            $user->save();

            $bannedUserInfo = [
                'id' => $user->id,
                'name' => $user->name,
                'is_banned' => $user->is_banned,
                'was_ever_banned' => $user->was_ever_banned,
                'moderation_info' => $user->moderation_info,
            ];

            return $this->successResponse($bannedUserInfo, 'User unbanned successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("User with ID $id does not exist", 'USER_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
