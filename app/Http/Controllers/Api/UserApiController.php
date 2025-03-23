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
use App\Traits\SelectableAttributes; // example $this->selectAttributes($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiPagination; // example $this->getPerPage($request, $query, 10);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Illuminate\Auth\Access\AuthorizationException;

class UserApiController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, ApiSorting, ApiFiltering, SelectableAttributes, ApiPagination, QueryBuilder, AuthorizesRequests;

    /**
     * The validation rules for the user data
     */
    public function getValidationRules($user): array {
        $validationRules = [
            'name' => ['required', 'string', 'max:255', new NotForbiddenName()],
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
                $user = $user->makeHidden(['is_banned', 'banned_at', 'ban_reason', 'banned_by', 'unbanned_at', 'unban_reason', 'unbanned_by']);
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

            $user->update([
                'name' => $validatedData['name'] ?? $user->name,
                'email' => $validatedData['email'] ?? $user->email,
                'password' => isset($validatedData['password']) ? bcrypt($validatedData['password']) : $user->password,
            ]);

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

            $user->delete();
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

            $this->authorize('banUser', $user);

            $validatedData = $request->validate([
                'ban_reason' => 'required|string',
            ]);

            $user->update([
                'is_banned' => true,
                'banned_at' => now(),
                'ban_reason' => $validatedData['ban_reason'],
                'banned_by' => $request->user()->id,
            ]);

            $bannedUserInfo = [
                'id' => $user->id,
                'name' => $user->name,
                'is_banned' => true,
                'banned_at' => $user->banned_at,
                'ban_reason' => $user->ban_reason
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

            $this->authorize('unbanUser', $user);

            $validatedData = $request->validate([
                'unban_reason' => 'required|string',
            ]);

            $user->update([
                'is_banned' => false,
                'unbanned_at' => now(),
                'unban_reason' => $validatedData['unban_reason'],
                'unbanned_by' => $request->user()->id,
            ]);

            $bannedUserInfo = [
                'id' => $user->id,
                'name' => $user->name,
                'is_banned' => false,
                'unbanned_at' => $user->unbanned_at,
                'unban_reason' => $user->unban_reason
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
     * Get the banned users.
     */
    public function getBannedUsers(Request $request): JsonResponse {
        try {
            $this->authorize('getBanned', User::class);

            $query = User::query()->where('is_banned', true);

            $query = $this->buildQuery($request, $query, 'user');

            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No banned users found with the given filters', 200);
            }

            return $this->successResponse($query, 'Banned users retrieved successfully', 200);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('You are not authorized to view banned users', 'UNAUTHORIZED_ACTION', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
