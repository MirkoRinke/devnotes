<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use App\Models\User;

use App\Rules\NotForbiddenName;

use App\Traits\ApiResponses; // example $this->successResponse($users, 'Users retrieved successfully', 200);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, 'user');

use App\Services\ModerationService;
use App\Services\UserRelationService;
use App\Services\GuestAccountService;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Illuminate\Auth\Access\AuthorizationException;

class UserApiController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, QueryBuilder, AuthorizesRequests;

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
     * List all users
     * 
     * Endpoint: GET /users
     *
     * Retrieves a complete list of users with all information, including moderation history.
     * 
     * Only administrators can access this endpoint.
     *
     * @group User Management
     *
     * @queryParam select string Comma-separated list of fields to include. Example: id,name,email
     * @queryParam sort string Field to sort by (prefix with - for descending). Example: -created_at
     * @queryParam filter[name] string Filter users by exact name match. Example: John
     * @queryParam filter[is_banned] string Filter users by ban status. Options:
     *        - "is:null": Users who are not banned
     *        - "is:not_null": Users who are currently banned
     *        - Date string: Match specific ban expiry date
     *        Example: /?filter[is_banned]=is:not_null
     * @queryParam startsWith[name] string Filter by name starting with value. Example: Jo
     * @queryParam endsWith[email] string Filter by email ending with value. Example: @example.com
     * 
     * @queryParam page integer Page number for pagination. Example: 1
     * @queryParam per_page integer Number of users per page (5-100). Example: 15 ( Default: 10 )
     * 
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Users retrieved successfully",
     *   "code": 200,
     *   "count": 2,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Admin User",
     *       "email": "admin@example.com",
     *       "email_verified_at": "2025-04-28T10:00:00.000000Z",
     *       "created_at": "2025-04-28T10:00:00.000000Z",
     *       "updated_at": "2025-04-28T10:00:00.000000Z",
     *       "display_name": "Admin",
     *       "role": "admin",
     *       "is_banned": null,
     *       "was_ever_banned": false,
     *       "moderation_info": null
     *     },
     *     {
     *       "id": 2,
     *       "name": "Regular User",
     *       "email": "user@example.com",
     *       "email_verified_at": "2025-04-28T10:00:00.000000Z",
     *       "created_at": "2025-04-28T10:00:00.000000Z",
     *       "updated_at": "2025-04-28T10:00:00.000000Z",
     *       "display_name": "User",
     *       "role": "user",
     *       "is_banned": null,
     *       "was_ever_banned": false,
     *       "moderation_info": null
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
     * @authenticated
     */
    public function index(Request $request): JsonResponse {
        try {
            $this->authorize('viewAny', User::class);

            if (User::count() === 0) {
                return $this->successResponse([], 'No users exist in the database', 200);
            }

            $query = User::query();

            $query = $this->buildQuery($request, $query, 'user');
            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No users found with the given filters', 200);
            }

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
     * If the requesting user is an administrator, additional information like ban status 
     * and moderation history will be included in the response.
     *
     * @group User Management
     * 
     * @urlParam id required The ID of the user to retrieve. Example: 1
     *
     * @queryParam select string Comma-separated list of fields to include. Example: id,name,email
     *
     * @response status=200 scenario="Success (Admin view)" {
     *   "status": "success",
     *   "message": "User retrieved successfully",
     *   "code": 200,
     *   "data": {
     *     "id": 1,
     *     "name": "Admin User",
     *     "email": "admin@example.com",
     *     "email_verified_at": "2025-04-28T10:00:00.000000Z",
     *     "created_at": "2025-04-28T10:00:00.000000Z",
     *     "updated_at": "2025-04-28T10:00:00.000000Z",
     *     "display_name": "Admin",
     *     "role": "admin",
     *     "is_banned": null,
     *     "was_ever_banned": false,
     *     "moderation_info": null
     *   }
     * }
     * 
     * @response status=200 scenario="Success (Regular user view)" {
     *   "status": "success", 
     *   "message": "User retrieved successfully",
     *   "code": 200,
     *   "data": {
     *     "id": 2,
     *     "name": "Regular User",
     *     "email": "user@example.com",
     *     "email_verified_at": "2025-04-28T10:00:00.000000Z",
     *     "created_at": "2025-04-28T10:00:00.000000Z",
     *     "updated_at": "2025-04-28T10:00:00.000000Z",
     *     "display_name": "User",
     *     "role": "user"
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
     * @authenticated
     */
    public function show(string $id, Request $request): JsonResponse {
        try {
            $query = User::query()->where('id', $id);

            $query = $this->buildQuerySelect($request, $query, 'user');
            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            // Need this because the select method returns only the query object
            $user = $query->firstOrFail();

            // If the user is not an admin, hide the banned user information
            if ($request->user()->role !== 'admin') {
                $user = $user->makeHidden(['is_banned', 'moderation_info']);
            }

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
     * Updates the specified user's information. Users can only update their own data,
     * while administrators can update any user.
     *
     * @group User Management
     *
     * @urlParam id required The ID of the user to update. Example: 1
     *
     * @bodyParam name string Name of the user (2-255 characters). Example: John Doe
     * @bodyParam email string Email address (must be unique). Example: john@example.com
     * @bodyParam password string Password (min 8 characters). Example: password123
     * @bodyParam password_confirmation string Password confirmation (must match password). Example: password123
     *
     * @bodyContent {
     *   "name": "John Doe",
     *   "email": "john@example.com",
     *   "password": "securePassword123",
     *   "password_confirmation": "securePassword123"
     * }
     * 
     * @bodyContent scenario="Update name only" {
     *   "name": "John Doe"
     * }
     * 
     * @bodyContent scenario="Update email only" {
     *    "email": "john@example.com"
     * }
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "User update successfully",
     *   "code": 200,
     *   "data": {
     *     "id": 2,
     *     "name": "John Doe",
     *     "email": "john@example.com",
     *     "email_verified_at": "2025-04-28T10:00:00.000000Z",
     *     "created_at": "2025-04-28T10:00:00.000000Z",
     *     "updated_at": "2025-04-29T15:30:45.000000Z",
     *     "display_name": "John",
     *     "role": "user",
     *     "is_banned": null,
     *     "was_ever_banned": false,
     *     "moderation_info": null
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

            $user = DB::transaction(function () use ($user, $validatedData) {
                $nameChanged = isset($validatedData['name']) && $validatedData['name'] !== $user->name;

                $user->update([
                    'name' => $validatedData['name'] ?? $user->name,
                    'email' => $validatedData['email'] ?? $user->email,
                    'password' => isset($validatedData['password']) ? bcrypt($validatedData['password']) : $user->password,
                ]);

                if ($nameChanged) {
                    $this->userRelationService->checkUsername($user);
                }
                return $user;
            });

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
     * Permanently removes a user account. For regular users, their content (posts and comments) 
     * will be transferred to the system user, while their reports and likes will be deleted.
     * Guest accounts receive special handling and will be recreated after deletion.
     * and their content (posts and comments) will be deleted.
     *
     * Only administrators can delete other users' accounts. Regular users can only delete their own account.
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
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
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
                return $this->handleGuestAccountDeletion($user);
            }

            DB::transaction(function () use ($user) {
                // Transfer all posts and comments to the system user (ID 3)
                $this->userRelationService->transferPosts($user);
                $this->userRelationService->transferComments($user);

                // Delete all reports and likes associated with the user
                $this->userRelationService->deleteReports($user);
                $this->userRelationService->deleteLikes($user);

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
     * Special handling for guest account deletion and recreation
     * 
     * This method deletes all posts and comments associated with the guest account,
     * deletes all reports and likes associated with the user, and then recreates the guest account.
     * 
     */
    public function handleGuestAccountDeletion(User $user): JsonResponse {
        try {
            DB::transaction(function () use ($user) {
                // Delete all posts and comments associated with the guest account
                $this->guestAccountService->deletePosts($user);
                $this->guestAccountService->deleteComments($user);

                // Delete all reports and likes associated with the user
                $this->userRelationService->deleteReports($user);
                $this->userRelationService->deleteLikes($user);

                $user->delete();
            });

            // Recreate the guest account outside the transaction
            $this->guestAccountService->createGuestAccount();

            return $this->successResponse(null, 'Guest account reset successfully', 200);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Ban a user
     * 
     * Endpoint: POST /users/{id}/ban
     *
     * Bans a user for a specified number of days. While technically temporary,
     * setting a very large number of days (e.g., 99999) effectively creates a
     * permanent ban. Only administrators can ban users. Banned users cannot 
     * log in until the ban expires.
     *
     * @group User Management
     *
     * @urlParam id required The ID of the user to ban. Example: 2
     *
     * @bodyParam moderation_reason string required Reason for banning the user. Example: Repeated violation of community guidelines
     * @bodyParam days integer required Number of days to ban the user (1-99999). Use a large value like 99999 for effectively permanent bans. Example: 7
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
     *     "id": 6,
     *     "name": "Max Mustermann6",
     *     "is_banned": "2025-05-12T23:48:23.000000Z",
     *     "was_ever_banned": true,
     *     "moderation_info": [
     *       {
     *         "user_id": 1,
     *         "username": "Max Mustermann1",
     *         "role": "admin",
     *         "timestamp": "2025-04-29T01:48:23+02:00",
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

            $user = $this->moderationService->handleModerationUpdate(
                $user,
                array_merge($validatedData, ['is_banned' => $bannedTime, 'was_ever_banned' => true]),
                $request,
                [],
                'banUser'
            );

            $user->save();

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
     * This will allow the user to log in again immediately.
     *
     * @group User Management
     *
     * @urlParam id required The ID of the user to unban. Example: 2
     *
     * @bodyParam moderation_reason string required Reason for unbanning the user. Example: Appeal approved
     * 
     * @bodyContent {
     *   "moderation_reason": "Appeal approved"
     * }
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "User unbanned successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 6,
     *     "name": "Max Mustermann6",
     *     "is_banned": null,
     *     "was_ever_banned": true,
     *     "moderation_info": [
     *       {
     *         "user_id": 1,
     *         "username": "Max Mustermann1",
     *         "role": "admin",
     *         "timestamp": "2025-04-29T01:50:23+02:00",
     *         "reason": "Appeal approved",
     *         "action": "unban"
     *       },
     *       {
     *         "user_id": 1,
     *         "username": "Max Mustermann1",
     *         "role": "admin",
     *         "timestamp": "2025-04-28T10:15:30+02:00",
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
                array_merge($validatedData, ['is_banned' => null]),
                $request,
                [],
                'unbanUser'
            );
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
