<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use App\Models\ForbiddenName;

use App\Traits\ApiResponses;
use App\Traits\QueryBuilder;
use App\Traits\RelationLoader;
use App\Traits\ApiInclude;
use App\Traits\CacheHelper;
use App\Traits\FieldManager;

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
    use ApiResponses, QueryBuilder, RelationLoader, ApiInclude, CacheHelper, FieldManager, AuthorizesRequests;

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
     * Setup the Forbidden Name Query
     * This method is used to setup the query for the forbidden names
     * It applies sorting, filtering, selecting, and pagination
     * It also loads the relations for the forbidden names
     * 
     * @param Request $request
     * @param mixed $query Builder|LengthAwarePaginator|Collection
     * @param string $methods The method to call for building the query
     * @return mixed Builder|LengthAwarePaginator|Collection
     * 
     * @example | $this->setupForbiddenNameQuery($request, $query, 'buildQuery')
     */
    protected function setupForbiddenNameQuery(Request $request, $query, $methods): mixed {
        $relationKeyFields = $this->getRelationKeyFields($request, ['user' => 'created_by_user_id']);

        $this->modifyRequestSelect($request, [...['id'], ...$relationKeyFields]);

        $this->loadUserRelation($request, $query, 'created_by_user_id');

        $query = $this->$methods($request, $query, 'forbidden_names');
        if ($query instanceof JsonResponse) {
            return $query;
        }

        return $query;
    }


    /**
     * List All Forbidden Names
     * 
     * Endpoint: GET /forbidden-names
     * 
     * Only users with the roles **admin** or **moderator** can access this endpoint.
     *
     * Retrieves a list of forbidden names with support for filtering, sorting, field selection, relation inclusion, and pagination.
     * **By default, results are paginated.**
     *
     * You can use the `*_fields` parameter for all relations (e.g. `user_fields`) to specify which fields should be returned for each relation.
     * 
     * @group ForbiddenName
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
     * @queryParam include  See [ApiInclude](#apiinclude) for relation inclusion details (e.g. user).
     * @see \App\Traits\ApiInclude::getRelationKeyFields()
     * 
     * @queryParam *_fields string See [ApiInclude](#apiinclude). When including a relation, specify fields to return. Example: user_fields=id,display_name
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest() for dynamic includes
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
     * Example URL: /forbidden-names
     * 
     * @response status=200 scenario="Success without user relation" {
     *   "status": "success",
     *   "message": "Forbidden names retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "admin",
     *       "match_type": "partial",
     *       "created_by_role": "system",
     *       "created_by_user_id": 2,
     *       "created_at": "2025-07-05T21:40:19.000000Z",
     *       "updated_at": "2025-07-05T21:40:19.000000Z"
     *     }
     *   ]
     * }
     *
     * Example URL: /forbidden-names/?include=user
     * 
     * @response status=200 scenario="Success with user relation" {
     *   "status": "success",
     *   "message": "Forbidden names retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "admin",
     *       "match_type": "partial",
     *       "created_by_role": "system",
     *       "created_by_user_id": 2,
     *       "created_at": "2025-07-05T21:40:19.000000Z",
     *       "updated_at": "2025-07-05T21:40:19.000000Z",
     *       "user": {
     *         "id": 2,
     *         "display_name": "System",
     *         "role": "system",
     *         "created_at": "2025-07-05T21:38:52.000000Z",
     *         "updated_at": "2025-07-05T21:38:52.000000Z",
     *         "is_banned": null,
     *         "was_ever_banned": false,
     *         "moderation_info": []
     *       }
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

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupForbiddenNameQuery($request, $query, 'buildQuery');
            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No forbidden names found', 200);
            }

            $query = $this->moderationFieldsVisibilityRelation($request, $query);

            $query = $this->checkForIncludedRelations($request, $query);

            $query = $this->controlVisibleFields($request, $originalSelectFields, $query);

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
     * Creates a new forbidden name entry that will be checked against user display names during registration and profile updates.
     * Only users with the roles **admin** or **moderator** can access this endpoint.
     * 
     * @group ForbiddenName
     *
     * @bodyParam name string required The forbidden name to add. Min: 2, Max: 255. Example: admin
     * @bodyParam match_type string required Type of matching to use. Allowed values: exact, partial. Example: partial
     * 
     * @bodyContent {
     *   "name": "admin",              || required, string, min:2, max:255
     *   "match_type": "partial"       || required, string, allowed: exact, partial
     * }
     * 
     * @response status=201 scenario="Forbidden name created" {
     *   "status": "success",
     *   "message": "Forbidden name created successfully",
     *   "code": 201,
     *   "count": 1,
     *   "data": {
     *     "id": 1,
     *     "name": "admin",
     *     "match_type": "partial",
     *     "created_by_role": "admin",
     *     "created_by_user_id": 1,
     *     "created_at": "2025-07-06T23:49:49.000000Z",
     *     "updated_at": "2025-07-06T23:49:49.000000Z"
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

            $user = $request->user();

            $validatedData = $request->validate(
                $this->getValidationRulesCreate(),
                $this->getValidationMessages('ForbiddenName')
            );

            // Check if the forbidden name already exists
            $existingForbiddenName = ForbiddenName::where('name', $validatedData['name'])->first();
            if ($existingForbiddenName) {
                return $this->errorResponse('Forbidden name already exists', 'FORBIDDEN_NAME_EXISTS', 409);
            }

            // Create a new ForbiddenName
            $forbiddenName = new ForbiddenName($validatedData);

            $forbiddenName->created_by_role = $user->role;
            $forbiddenName->created_by_user_id = $user->id;

            $forbiddenName->save();

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
     * Retrieves a specific forbidden name by its ID.
     * Only users with the roles **admin** or **moderator** can access this endpoint.
     * 
     * @group ForbiddenName
     * 
     * @urlParam id integer required The ID of the forbidden name. Example: 1
     *
     * @queryParam select   See [ApiSelectable](#apiselectable) for field selection details.
     * @see \App\Traits\ApiSelectable::select()
     * 
     * @queryParam filter   See [ApiFiltering](#apifiltering) for filtering details.
     * @see \App\Traits\ApiFiltering::filter()
     * 
     * @queryParam include  See [ApiInclude](#apiinclude) for relation inclusion details (e.g. user).
     * @see \App\Traits\ApiInclude::getRelationKeyFields()
     * 
     * @queryParam *_fields string See [ApiInclude](#apiinclude). When including a relation, specify fields to return. Example: user_fields=id,display_name
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest() for dynamic includes
     *
     * Example URL: /forbidden-names/1
     *
     * @response status=200 scenario="Success without user relation" {
     *   "status": "success",
     *   "message": "Forbidden name retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 1,
     *     "name": "admin",
     *     "match_type": "partial",
     *     "created_by_role": "system",
     *     "created_by_user_id": 2,
     *     "created_at": "2025-07-05T21:40:19.000000Z",
     *     "updated_at": "2025-07-05T21:40:19.000000Z"
     *   }
     * }
     * 
     * Example URL: /forbidden-names/1/?include=user
     *
     * @response status=200 scenario="Success with user relation" {
     *   "status": "success",
     *   "message": "Forbidden name retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 1,
     *     "name": "admin",
     *     "match_type": "partial",
     *     "created_by_role": "system",
     *     "created_by_user_id": 2,
     *     "created_at": "2025-07-05T21:40:19.000000Z",
     *     "updated_at": "2025-07-05T21:40:19.000000Z",
     *     "user": {
     *       "id": 2,
     *       "display_name": "System",
     *       "role": "system",
     *       "created_at": "2025-07-05T21:38:52.000000Z",
     *       "updated_at": "2025-07-05T21:38:52.000000Z",
     *       "is_banned": null,
     *       "was_ever_banned": false,
     *       "moderation_info": []
     *     }
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
    public function show(Request $request, $id) {
        try {
            $this->authorize('view', ForbiddenName::class);

            $query = ForbiddenName::query()->where('id', $id);

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupForbiddenNameQuery($request, $query, 'buildQuerySelect');
            if ($query instanceof JsonResponse) {
                return $query;
            }

            $query = $query->firstOrFail();

            $query = $this->moderationFieldsVisibilityRelation($request, $query);

            $query = $this->checkForIncludedRelations($request, $query);

            $query = $this->controlVisibleFields($request, $originalSelectFields, $query);

            return $this->successResponse($query, 'Forbidden name retrieved successfully', 200);
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
     * Only users with the roles **admin** or **moderator** can access this endpoint.
     * 
     * **Note:** Because only `name` and `match_type` can be changed, updating a forbidden name is practically the same as creating a new one.
     * 
     * @group ForbiddenName
     *
     * @urlParam id integer required The ID of the forbidden name to update. Example: 3
     * 
     * @bodyParam name string optional The new forbidden name. Min: 2, Max: 255. Example: root
     * @bodyParam match_type string optional Type of matching to use. Allowed values: exact, partial. Example: exact
     * 
     * @bodyContent {
     *   "name": "root",                || optional, string, min:2, max:255
     *   "match_type": "exact"          || optional, string, allowed: exact, partial
     * }
     * 
     * @response status=200 scenario="Forbidden name updated" {
     *   "status": "success",
     *   "message": "Forbidden name updated successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 3,
     *     "name": "root",
     *     "match_type": "exact",
     *     "created_by_role": "admin",
     *     "created_by_user_id": 1,
     *     "created_at": "2025-07-06T23:49:49.000000Z",
     *     "updated_at": "2025-07-07T00:01:27.000000Z"
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

            $user = $request->user();

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
            $forbiddenName->fill($validatedData);

            $forbiddenName->created_by_role = $user->role;
            $forbiddenName->created_by_user_id = $user->id;

            $forbiddenName->save();

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
     * Only users with the roles **admin** or **moderator** can access this endpoint.
     * 
     * @group ForbiddenName
     *
     * @urlParam id integer required The ID of the forbidden name to delete. Example: 3
     * 
     * @response status=200 scenario="Forbidden name deleted" {
     *   "status": "success",
     *   "message": "Forbidden name: administrator with match type: partial deleted successfully",
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
