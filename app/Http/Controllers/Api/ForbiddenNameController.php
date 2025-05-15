<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use App\Models\ForbiddenName;

use App\Traits\ApiResponses;
use App\Traits\ApiInclude;
use App\Traits\QueryBuilder;
use App\Traits\CacheHelper;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * ForbiddenNameController
 *
 * This controller handles the management of forbidden names in the system.
 * Forbidden names are names that users are not allowed to use when registering or updating their profiles.
 * 
 */
class ForbiddenNameController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, QueryBuilder, ApiInclude, CacheHelper, AuthorizesRequests;

    /**
     * The validation rules for the create method
     * 
     * @return array
     * 
     * @example | $this->getValidationRulesCreate()
     */
    public function getValidationRulesCreate(): array {
        $validationRulesCreate = [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'match_type' => ['required', 'string', 'in:exact,partial'],
        ];
        return $validationRulesCreate;
    }


    /**
     * The validation rules for the Update method
     * 
     * @return array
     * 
     * @example | $this->getValidationRulesUpdate()
     */
    public function getValidationRulesUpdate(): array {
        $validationRulesUpdate = [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'match_type' => ['sometimes', 'required', 'string', 'in:exact,partial'],
        ];
        return $validationRulesUpdate;
    }


    /**
     * List All Forbidden Names
     * 
     * Endpoint: GET /forbidden-names
     *
     * Retrieves a list of all forbidden names configured in the system.
     * These are names that users are not allowed to use when registering or updating their profiles.
     * 
     * Note: This endpoint requires admin or moderator permission.
     * 
     * @group ForbiddenName
     *
     * @queryParam select string Select specific fields (id,name,match_type,etc). Example: select=id,name,match_type
     * @queryParam sort string Field to sort by (prefix with - for DESC order). Example: sort=-created_at
     * @queryParam filter[field] string Filter by specific fields. Example: filter[match_type]=exact
     * 
     * @queryParam startsWith[field] string Filter by fields that start with a specific value. Example: startsWith[name]=ad
     * @queryParam endsWith[field] string Filter by fields that end with a specific value. Example: endsWith[name]=tor
     * 
     * @queryParam page integer Page number for pagination. Example: page=2
     * @queryParam per_page integer Number of items per page. Example: per_page=15 (Default: 10)
     * 
     * Example URL: /forbidden-names
     * 
     * @response status=200 scenario="Forbidden names retrieved" {
     *   "status": "success",
     *   "message": "Forbidden names retrieved successfully",
     *   "code": 200,
     *   "count": 3,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "admin",
     *       "match_type": "exact",
     *       "created_by_role": "admin",
     *       "created_by_user_id": 1,
     *       "created_at": "2025-04-12T14:22:18.000000Z",
     *       "updated_at": "2025-04-12T14:22:18.000000Z"
     *     },
     *     {
     *       "id": 2,
     *       "name": "moderator",
     *       "match_type": "exact",
     *       "created_by_role": "admin",
     *       "created_by_user_id": 1,
     *       "created_at": "2025-04-12T14:22:38.000000Z",
     *       "updated_at": "2025-04-12T14:22:38.000000Z"
     *     },
     *     {
     *       "id": 3,
     *       "name": "system",
     *       "match_type": "partial",
     *       "created_by_role": "moderator",
     *       "created_by_user_id": 2,
     *       "created_at": "2025-04-15T09:11:23.000000Z",
     *       "updated_at": "2025-04-15T09:11:23.000000Z"
     *     }
     *   ]
     * }
     * 
     * Example URL: /forbidden-names?select=id,name,match_type
     * 
     * @response status=200 scenario="Forbidden names retrieved with select" {
     *  "status": "success",
     *  "message": "Forbidden names retrieved successfully",
     *  "code": 200,
     *  "count": 3,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "admin",
     *       "match_type": "exact",
     *     },
     *     {
     *       "id": 2,
     *       "name": "moderator",
     *       "match_type": "exact",
     *     },
     *     {
     *       "id": 3,
     *       "name": "system",
     *       "match_type": "partial",
     *     }
     *   ]
     * }
     *
     * @response status=200 scenario="No forbidden names found" {
     *   "status": "success",
     *   "message": "No forbidden names found",
     *   "code": 200,
     *   "count": 0,
     *   "data": []
     * }
     *
     * @response status=404 scenario="Database empty" {
     *   "status": "error",
     *   "message": "No forbidden names found",
     *   "code": 404,
     *   "errors": "NOT_FOUND"
     * }
     *
     * @response status=403 scenario="Unauthorized access" {
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
    public function index(Request $request) {
        try {
            $this->authorize('viewAny', ForbiddenName::class);

            if (ForbiddenName::count() == 0) {
                return $this->errorResponse('No forbidden names found', 'NOT_FOUND', 404);
            }

            $query = ForbiddenName::query();

            $query = $this->buildQuery($request, $query, 'forbidden_names');

            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No forbidden names found', 200);
            }

            return $this->successResponse($query, 'Forbidden names retrieved successfully', 200);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Create a New Forbidden Name
     * 
     * Endpoint: POST /forbidden-names
     *
     * Creates a new forbidden name entry that will be checked against user display names
     * during registration and profile updates.
     * 
     * Note: This endpoint requires admin or moderator permission.
     * 
     * @group ForbiddenName
     *
     * @bodyParam name string required The forbidden name to add. Example: offensive
     * @bodyParam match_type string required Type of matching to use (exact or partial). Example: partial
     * 
     * @bodyContent {
     *   "name": "offensive",
     *   "match_type": "partial"
     * }
     * 
     * @response status=201 scenario="Forbidden name created" {
     *   "status": "success",
     *   "message": "Forbidden name created successfully",
     *   "code": 201,
     *   "count": 1,
     *   "data": {
     *     "id": 4,
     *     "name": "offensive",
     *     "match_type": "partial",
     *     "created_by_role": "admin",
     *     "created_by_user_id": 1,
     *     "created_at": "2025-05-04T20:15:34.000000Z",
     *     "updated_at": "2025-05-04T20:15:34.000000Z"
     *   }
     * }
     *
     * @response status=409 scenario="Forbidden name already exists" {
     *   "status": "error",
     *   "message": "Forbidden name already exists",
     *   "code": 409,
     *   "errors": "FORBIDDEN_NAME_EXISTS"
     * }
     *
     * @response status=403 scenario="Unauthorized access" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     *
     * @response status=422 scenario="Validation failed" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "name": ["NAME_FIELD_REQUIRED"],
     *     "match_type": ["MATCH_TYPE_INVALID_OPTION"]
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
    public function store(Request $request) {
        try {
            $this->authorize('create', ForbiddenName::class);

            $validatedData = $request->validate(
                $this->getValidationRulesCreate(),
                $this->getValidationMessages('ForbiddenName')
            );

            // Check if the forbidden name already exists
            $existingForbiddenName = ForbiddenName::where('name', $validatedData['name'])->first();
            if ($existingForbiddenName) {
                return $this->errorResponse('Forbidden name already exists', 'FORBIDDEN_NAME_EXISTS', 409);
            }

            // Create the forbidden name
            $forbiddenName = ForbiddenName::create([
                ...$validatedData,
                'created_by_role' => $request->user()->role,
                'created_by_user_id' => $request->user()->id
            ]);

            $this->forgetForbiddenNameCache();

            return $this->successResponse($forbiddenName, 'Forbidden name created successfully', 201);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Get Single Forbidden Name
     * 
     * Endpoint: GET /forbidden-names/{id}
     *
     * Retrieves detailed information about a specific forbidden name by its ID.
     * 
     * Note: This endpoint requires admin or moderator permission.
     * 
     * @group ForbiddenName
     *
     * @urlParam id integer required The ID of the forbidden name to retrieve. Example: 1
     * @queryParam select string Select specific fields to return. Example: select=id,name,match_type
     * 
     * Example URL: /forbidden-names/1
     * 
     * @response status=200 scenario="Forbidden name retrieved" {
     *   "status": "success",
     *   "message": "Forbidden name retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 1,
     *     "name": "admin",
     *     "match_type": "exact",
     *     "created_by_role": "admin",
     *     "created_by_user_id": 1,
     *     "created_at": "2025-04-12T14:22:18.000000Z",
     *     "updated_at": "2025-04-12T14:22:18.000000Z"
     *   }
     * }
     * 
     * Example URL: /forbidden-names/1?select=id,name,match_type
     * 
     * @response status=200 scenario="Forbidden name retrieved with select" {
     *   "status": "success",
     *   "message": "Forbidden name retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 1,
     *     "name": "admin",
     *     "match_type": "exact"
     *   }
     * }
     *
     * @response status=403 scenario="Unauthorized access" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     *
     * @response status=404 scenario="Forbidden name not found" {
     *   "status": "error",
     *   "message": "Forbidden name not found",
     *   "code": 404,
     *   "errors": "NOT_FOUND"
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
    public function show(Request $request, $id) {
        try {
            $this->authorize('view', ForbiddenName::class);

            $query = ForbiddenName::query()->where('id', $id);

            $query = $this->buildQuerySelect($request, $query, 'forbidden_names');

            if ($query instanceof JsonResponse) {
                return $query;
            }

            $forbiddenName = $query->firstOrFail();

            return $this->successResponse($forbiddenName, 'Forbidden name retrieved successfully', 200);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Forbidden name not found', 'NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Update a Forbidden Name
     * 
     * Endpoint: PATCH /forbidden-names/{id}
     *
     * Updates an existing forbidden name entry with new information.
     * 
     * Note: This endpoint requires admin or moderator permission.
     * 
     * @group ForbiddenName
     *
     * @urlParam id integer required The ID of the forbidden name to update. Example: 1
     * @bodyParam name string The new name to use. Example: offensive_term
     * @bodyParam match_type string Type of matching to use (exact or partial). Example: exact
     * 
     * @bodyContent {
     *   "name": "offensive_term",
     *   "match_type": "exact"
     * }
     * 
     * @response status=200 scenario="Forbidden name updated" {
     *   "status": "success",
     *   "message": "Forbidden name updated successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 1,
     *     "name": "offensive_term",
     *     "match_type": "exact",
     *     "created_by_role": "admin",
     *     "created_by_user_id": 1,
     *     "created_at": "2025-04-15T09:11:23.000000Z",
     *     "updated_at": "2025-05-04T21:30:12.000000Z"
     *   }
     * }
     * 
     * @response status=422 scenario="No fields provided" {
     *   "status": "error",
     *   "message": "At least one field must be provided for update",
     *   "code": 422,
     *   "errors": "NO_FIELDS_PROVIDED"
     * }
     * 
     * @response status=409 scenario="Forbidden name already exists" {
     *   "status": "error",
     *   "message": "Forbidden name already exists",
     *   "code": 409,
     *   "errors": "FORBIDDEN_NAME_EXISTS"
     * }
     *
     * @response status=403 scenario="Unauthorized access" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     *
     * @response status=422 scenario="Validation failed" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "name": ["NAME_MIN_LENGTH"],
     *     "match_type": ["MATCH_TYPE_INVALID_OPTION"]
     *   }
     * }
     *
     * @response status=404 scenario="Forbidden name not found" {
     *   "status": "error",
     *   "message": "Forbidden name not found",
     *   "code": 404,
     *   "errors": "NOT_FOUND"
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
    public function update(Request $request, $id) {
        try {
            $this->authorize('update', ForbiddenName::class);

            $validatedData = $request->validate(
                $this->getValidationRulesUpdate(),
                $this->getValidationMessages('ForbiddenName')
            );

            if (empty($validatedData)) {
                return $this->errorResponse('At least one field must be provided for update', 'NO_FIELDS_PROVIDED', 422);
            }

            // Find the forbidden name by ID
            $forbiddenName = ForbiddenName::findOrFail($id);

            if (isset($validatedData['name']) && $validatedData['name'] !== $forbiddenName->name) {
                $existingName = ForbiddenName::where('name', $validatedData['name'])->first();

                if ($existingName) {
                    return $this->errorResponse('Forbidden name already exists', 'FORBIDDEN_NAME_EXISTS', 409);
                }
            }

            // Update the forbidden name
            $forbiddenName->update([
                ...$validatedData,
                'created_by_role' => $request->user()->role,
                'created_by_user_id' => $request->user()->id
            ]);

            $this->forgetForbiddenNameCache();

            return $this->successResponse($forbiddenName, 'Forbidden name updated successfully', 200);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Forbidden name not found', 'NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Delete a Forbidden Name
     * 
     * Endpoint: DELETE /forbidden-names/{id}
     *
     * Permanently removes a forbidden name from the system.
     * 
     * Note: This endpoint requires admin or moderator permission.
     * 
     * @group ForbiddenName
     *
     * @urlParam id integer required The ID of the forbidden name to delete. Example: 1
     * 
     * @response status=200 scenario="Forbidden name deleted" {
     *   "status": "success",
     *   "message": "Forbidden name: admin with match type: partial deleted successfully",
     *   "code": 200,
     *   "count": 0,
     *   "data": null
     * }
     *
     * @response status=403 scenario="Unauthorized access" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     *
     * @response status=404 scenario="Forbidden name not found" {
     *   "status": "error",
     *   "message": "Forbidden name not found",
     *   "code": 404,
     *   "errors": "NOT_FOUND"
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
    public function destroy($id) {
        try {
            $this->authorize('delete', ForbiddenName::class);

            // Find the forbidden name by ID
            $forbiddenName = ForbiddenName::findOrFail($id);

            // Get the name of the forbidden name
            $name = $forbiddenName->name;
            $matchType = $forbiddenName->match_type;

            // Delete the forbidden name
            $forbiddenName->delete();

            $this->forgetForbiddenNameCache();

            return $this->successResponse(null, "Forbidden name: $name with match type: $matchType deleted successfully", 200);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Forbidden name not found', 'NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
