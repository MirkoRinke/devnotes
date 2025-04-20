<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ForbiddenName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\User;

use App\Rules\NotForbiddenName;

use App\Traits\ApiResponses; // example $this->successResponse($users, 'Users retrieved successfully', 200);
use App\Traits\ApiSorting; // example $this->sort($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiFiltering; // example $this->filter($request, $query, [ 'name', 'email']);
use App\Traits\ApiSelectable; // example $this->selectAttributes($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiPagination; // example $this->getPerPage($request, $query, 10);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);

use App\Services\ModerationService;
use App\Services\UserRelationService;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class UserApiController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, ApiSorting, ApiFiltering, ApiSelectable, ApiPagination, QueryBuilder, AuthorizesRequests;


    protected $moderationService;
    protected $userRelationService;

    public function __construct(ModerationService $moderationService, UserRelationService $userRelationService) {
        $this->moderationService = $moderationService;
        $this->userRelationService = $userRelationService;
    }

    /**
     * The validation rules for the user data
     */
    public function getValidationRules($user): array {
        $validationRules = [
            'name' => ['required', 'string', 'min:2', 'max:255', new NotForbiddenName()],
            'email' => 'required|string|email|unique:users,email,' . $user->id,
            'password' => 'required|string|min:8|confirmed',
        ];
        return $validationRules;
    }

    /**
     * Display a listing of the resource.
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
            return $this->errorResponse('You are not authorized to update this post', 'UNAUTHORIZED_ACTION', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Display the specified resource.
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
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse {
        try {
            $user = User::findOrFail($id);

            $this->authorize('update', $user);

            $validatedData = $request->validate(
                $this->getValidationRules($user),
                $this->getValidationMessages()
            );

            $user = DB::transaction(function () use ($user, $validatedData) {
                $nameChanged = isset($validatedData['name']) && $validatedData['name'] !== $user->name;

                $user->update([
                    'name' => $validatedData['name'] ?? $user->name,
                    'email' => $validatedData['email'] ?? $user->email,
                    'password' => isset($validatedData['password']) ? bcrypt($validatedData['password']) : $user->password,
                ]);

                // Create profile and run moderation
                if ($nameChanged) {
                    $this->userRelationService->checkUsername($user);
                }
                return $user;
            });

            return $this->successResponse($user, 'User update successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("User with ID $id does not exist", 'USER_NOT_FOUND', 404);
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
    public function destroy(string $id): JsonResponse {
        try {
            $user = User::findOrFail($id);

            $this->authorize('delete', $user);

            $user = DB::transaction(function () use ($user) {
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
     * banUser the specified user.
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
                $this->getValidationMessages()
            );

            $days = $validatedData['days'];
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
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * unbanUser the specified user.
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
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Get users with ban history.
     */
    public function getUsersWithBanHistory(Request $request): JsonResponse {
        try {
            $this->authorize('getBanned', User::class);

            $query = User::query()->where('was_ever_banned', true);

            $query = $this->buildQuery($request, $query, 'user');

            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No users found with the given filters', 200);
            }

            return $this->successResponse($query, 'Users retrieved successfully', 200);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('You are not authorized to view users', 'UNAUTHORIZED_ACTION', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
