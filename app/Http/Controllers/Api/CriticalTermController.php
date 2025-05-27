<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use App\Models\CriticalTerm;

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
 * Controller handling all critical term-related API endpoints.
 * 
 * Critical terms are words/phrases that are flagged for moderation with different severity levels.
 * This controller provides functionality to create, read, update and delete critical terms,
 * with comprehensive validation and cache management.
 */
class CriticalTermController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, QueryBuilder, ApiInclude, CacheHelper, AuthorizesRequests, RelationLoader, FieldManager;

    /**
     * The validation rules for the create method
     * 
     * @return array
     * 
     * @example | $this->getValidationRulesCreate()
     */
    public function getValidationRulesCreate(): array {
        $validationRulesUpdate = [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'language' => ['required', 'string', 'min:2', 'max:255'],
            'severity' => ['required', 'integer', 'between:1,5'],
        ];
        return $validationRulesUpdate;
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
            'language' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'severity' => ['sometimes', 'required', 'integer', 'between:1,5'],
        ];
        return $validationRulesUpdate;
    }

    /**
     * Setup the query for the critical terms
     * This method is used to setup the query for the critical terms
     * It applies sorting, filtering, selecting, and pagination
     * It also loads the relations for the critical terms
     * 
     * @param Request $request
     * @param mixed $query Builder|LengthAwarePaginator|Collection
     * @param string $methods The method to call for building the query
     * @return mixed Builder|LengthAwarePaginator|Collection
     * 
     * @example | $this->setupCriticalTermQuery($request, $query, 'buildQuery')
     */
    protected function setupCriticalTermQuery(Request $request, $query, $methods): mixed {
        $relationKeyFields = $this->getRelationKeyFields($request, ['user' => 'created_by_user_id']);

        $this->modifyRequestSelect($request, [...['id'], ...$relationKeyFields]);

        $this->loadUserRelation($request, $query);

        $query = $this->$methods($request, $query, 'critical_terms');
        if ($query instanceof JsonResponse) {
            return $query;
        }

        return $query;
    }


    /**
     * Load the user relation
     * 
     * @param Request $request
     * @param mixed $query Builder|LengthAwarePaginator|Collection
     * @return mixed Builder|LengthAwarePaginator|Collection
     * 
     * @example | $this->loadUserRelation($request, $query)
     */
    private function loadUserRelation(Request $request, $query): mixed {
        if ($request->has('include') && in_array('user', explode(',', $request->input('include')))) {
            $query = $this->loadRelations($request, $query, [
                ['relation' => 'user', 'foreignKey' => 'created_by_user_id', 'columns' => $this->getRelationFieldsFromRequest($request, 'user', [], ['id', 'display_name', 'role', 'created_at', 'updated_at', 'is_banned', 'was_ever_banned', 'moderation_info'])],
            ]);
        }
        return $query;
    }

    /**
     * Get All Critical Terms
     * 
     * Endpoint: GET /critical-terms
     *
     * Retrieves a list of all critical terms, optionally filtered and sorted.
     * Critical terms are words or phrases flagged for moderation with varying severity levels.
     * 
     * @group CriticalTerms
     *
     * @queryParam select string Select specific fields (id,name,language,etc). Example: select=id,name,severity
     * @queryParam sort string Field to sort by (prefix with - for DESC order). Example: sort=-severity
     * @queryParam filter[field] string Filter by specific fields. Example: filter[language]=en
     * 
     * @queryParam startsWith[field] string Filter by fields that start with a specific value. Example: startsWith[name]=off
     * @queryParam endsWith[field] string Filter by fields that end with a specific value. Example: endsWith[name]=term
     * 
     * @queryParam page integer Page number for pagination. Example: page=2
     * @queryParam per_page integer Number of items per page. Example: per_page=15 (Default: 10)
     * 
     * @queryParam include string Optional. Include related resources: user. Example: include=user
     * @queryParam user_fields string When including user relation, specify fields to return. 
     *                              Available fields: id,display_name,role,created_at,updated_at,is_banned,was_ever_banned,moderation_info
     *                              Example: user_fields=id,name,display_name
     * 
     * Example URL: /critical-terms
     * 
     * @response status=200 scenario="All Critical Terms retrieved" {
     *   "status": "success",
     *   "message": "Critical Terms retrieved successfully",
     *   "code": 200,
     *   "count": 3,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "inappropriate_word",
     *       "language": "en",
     *       "severity": 3,
     *       "created_by_role": "admin",
     *       "created_by_user_id": 1,
     *       "created_at": "2025-03-12T14:22:08.000000Z",
     *       "updated_at": "2025-03-12T14:22:08.000000Z"
     *     },
     *     {
     *       "id": 2,
     *       "name": "offensive_term",
     *       "language": "en",
     *       "severity": 5,
     *       "created_by_role": "admin",
     *       "created_by_user_id": 1,
     *       "created_at": "2025-03-15T10:45:32.000000Z",
     *       "updated_at": "2025-03-15T10:45:32.000000Z"
     *     },
     *     {
     *       "id": 3,
     *       "name": "mild_term",
     *       "language": "de",
     *       "severity": 1,
     *       "created_by_role": "admin",
     *       "created_by_user_id": 1,
     *       "created_at": "2025-04-05T09:13:27.000000Z",
     *       "updated_at": "2025-04-05T09:13:27.000000Z"
     *     }
     *   ]
     * }
     * 
     * Example URL: /critical-terms/?select=id,name,severity,language&include=user&user_fields=id,display_name
     * 
     * @response status=200 scenario="Filtered Critical Terms retrieved" {
     *   "status": "success",
     *   "message": "Critical Terms retrieved successfully",
     *   "code": 200,
     *   "count": 2,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "offensive_term",
     *       "severity": 5,
     *       "user": {
     *         "id": 2,
     *         "display_name": "Maxi2"
     *       }
     *     },
     *     {
     *       "id": 2,
     *       "name": "inappropriate_word",
     *       "severity": 3,
     *        "user": {
     *         "id": 2,
     *         "display_name": "Maxi2"
     *       }
     *     }
     *   ]
     * }
     *
     * @response status=200 scenario="No critical terms found" {
     *   "status": "success",
     *   "message": "No Critical Terms found",
     *   "code": 200,
     *   "count": 0,
     *   "data": []
     * }
     * 
     * @response status=404 scenario="Critical terms table empty" {
     *   "status": "error",
     *   "message": "No Critical Terms found",
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
            $this->authorize('viewAny', CriticalTerm::class);

            if (CriticalTerm::count() == 0) {
                return $this->errorResponse('No Critical Terms found', 'NOT_FOUND', 404);
            }

            $query = CriticalTerm::query();

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupCriticalTermQuery($request, $query, 'buildQuery');
            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No Critical Terms found', 200);
            }

            $query = $this->moderationFieldsVisibilityRelation($request, $query);

            $query = $this->checkForIncludedRelations($request, $query);

            $query = $this->controlVisibleFields($request, $originalSelectFields, $query);

            return $this->successResponse($query, 'Critical Terms retrieved successfully', 200);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Create New Critical Term
     * 
     * Endpoint: POST /critical-terms
     *
     * Creates a new critical term in the system.
     * Critical terms are words or phrases flagged for moderation with varying severity levels.
     * 
     * @group CriticalTerms
     * 
     * @bodyParam name string required The name of the critical term. Example: offensive_word
     * @bodyParam language string required The language code of the term. Example: en
     * @bodyParam severity integer required The severity level of the term (1-5). Example: 4
     * 
     * @bodyContent {
     *   "name": "offensive_word",
     *   "language": "en",
     *   "severity": 4
     * }
     * 
     * @response status=201 scenario="Critical Term created" {
     *   "status": "success",
     *   "message": "Critical Term created successfully",
     *   "code": 201,
     *   "count": 1,
     *   "data": {
     *     "id": 4,
     *     "name": "offensive_word",
     *     "language": "en",
     *     "severity": 4,
     *     "created_by_role": "admin",
     *     "created_by_user_id": 1,
     *     "created_at": "2025-05-04T15:22:43.000000Z",
     *     "updated_at": "2025-05-04T15:22:43.000000Z"
     *   }
     * }
     *
     * @response status=409 scenario="Critical Term already exists" {
     *   "status": "error",
     *   "message": "Critical Term already exists",
     *   "code": 409,
     *   "errors": "CRITICAL_TERM_EXISTS"
     * }
     *
     * @response status=403 scenario="Unauthorized" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     * 
     * @response status=422 scenario="Validation Error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "name": ["NAME_FIELD_REQUIRED"],
     *     "language": ["LANGUAGE_FIELD_REQUIRED"],
     *     "severity": ["SEVERITY_MUST_BE_BETWEEN_1_AND_5."]
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
            // Check if the user is authorized to create Critical Terms
            $this->authorize('create', CriticalTerm::class);

            $validatedData = $request->validate(
                $this->getValidationRulesCreate(),
                $this->getValidationMessages('CriticalTerm')
            );

            // Check if the Critical Term already exists
            $existingCriticalTerm = CriticalTerm::where('name', $validatedData['name'])->first();

            if ($existingCriticalTerm) {
                return $this->errorResponse('Critical Term already exists', 'CRITICAL_TERM_EXISTS', 409);
            }

            // Create the Critical Term
            $criticalTerm = CriticalTerm::create([
                ...$validatedData,
                'created_by_role' => $request->user()->role,
                'created_by_user_id' => $request->user()->id
            ]);

            $this->forgetCacheByModelType('App\Models\CriticalTerm');

            return $this->successResponse($criticalTerm, 'Critical Term created successfully', 201);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Get Single Critical Term
     * 
     * Endpoint: GET /critical-terms/{id}
     *
     * Retrieves a specific critical term by its ID.
     * Critical terms are words or phrases flagged for moderation with varying severity levels.
     * 
     * @group CriticalTerms
     * 
     * @urlParam id required integer The ID of the critical term. Example: 2
     *
     * @queryParam select string Select specific fields (id,name,language,etc). Example: select=id,name,severity
     * 
     * @queryParam include string Optional. Include related resources: user. Example: include=user
     * @queryParam user_fields string When including user relation, specify fields to return. 
     *                              Available fields: id,display_name,role,created_at,updated_at,is_banned,was_ever_banned,moderation_info
     *                              Example: user_fields=id,name,display_name
     * 
     * Example URL: /critical-terms/2
     * 
     * @response status=200 scenario="Critical Term retrieved (full data)" {
     *   "status": "success",
     *   "message": "Critical Term retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 2,
     *     "name": "offensive_term",
     *     "language": "en",
     *     "severity": 5,
     *     "created_by_role": "admin",
     *     "created_by_user_id": 1,
     *     "created_at": "2025-03-15T10:45:32.000000Z",
     *     "updated_at": "2025-03-15T10:45:32.000000Z"
     *   }
     * }
     * 
     * Example URL with select: /critical-terms/2/?select=id,name,severity,language&include=user&user_fields=id,display_name
     * 
     * @response status=200 scenario="Critical Term with selected fields and included user relation" {
     *   "status": "success",
     *   "message": "Critical Term retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 2,
     *     "name": "offensive_term",
     *     "severity": 5,
     *     "language": "en",
     *     "user": {
     *         "id": 2,
     *         "display_name": "Maxi2"
     *     }
     *   }
     * }
     *
     * @response status=404 scenario="Critical Term not found" {
     *   "status": "error",
     *   "message": "Critical Term not found",
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
            $this->authorize('view', CriticalTerm::class);

            $query = CriticalTerm::query()->where('id', $id);

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupCriticalTermQuery($request, $query, 'buildQuerySelect');
            if ($query instanceof JsonResponse) {
                return $query;
            }

            // Need this because the select method returns only the query object
            $query = $query->firstOrFail();

            $query = $this->moderationFieldsVisibilityRelation($request, $query);

            $query = $this->checkForIncludedRelations($request, $query);

            $query = $this->controlVisibleFields($request, $originalSelectFields, $query);

            return $this->successResponse($query, 'Critical Term retrieved successfully', 200);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Critical Term not found', 'NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Update Critical Term
     * 
     * Endpoint: PATCH /critical-terms/{id}
     *
     * Updates an existing critical term in the system.
     * All fields are optional, but at least one field must be provided.
     * 
     * @group CriticalTerms
     * 
     * @urlParam id required integer The ID of the critical term to update. Example: 2
     *
     * @bodyParam name string optional The name of the critical term. Example: updated_offensive_term
     * @bodyParam language string optional The language code of the term. Example: de
     * @bodyParam severity integer optional The severity level of the term (1-5). Example: 3
     * 
     * @bodyContent {
     *   "name": "updated_offensive_term",  || Optional but at least one field must be provided
     *   "language": "de",                  || Optional but at least one field must be provided 
     *   "severity": 3                      || Optional but at least one field must be provided
     * }
     * 
     * @response status=200 scenario="Critical Term updated" {
     *   "status": "success",
     *   "message": "Critical Term updated successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 2,
     *     "name": "updated_offensive_term",
     *     "language": "en",
     *     "severity": 3,
     *     "created_by_role": "admin",
     *     "created_by_user_id": 1,
     *     "created_at": "2025-03-15T10:45:32.000000Z",
     *     "updated_at": "2025-05-04T16:30:15.000000Z"
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
     * @response status=409 scenario="Critical Term name already exists" {
     *   "status": "error",
     *   "message": "Critical Term already exists",
     *   "code": 409,
     *   "errors": "CRITICAL_TERM_EXISTS"
     * }
     * 
     * @response status=403 scenario="Unauthorized" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     *
     * @response status=422 scenario="Validation Error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "severity": ["SEVERITY_MUST_BE_BETWEEN_1_AND_5"]
     *   }
     * }
     * 
     * @response status=404 scenario="Critical Term not found" {
     *   "status": "error",
     *   "message": "Critical Term not found",
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
    public function update(Request $request, string $id) {
        try {
            // Check if the user is authorized to update Critical Terms
            $this->authorize('update', CriticalTerm::class);

            $validatedData = $request->validate(
                $this->getValidationRulesUpdate(),
                $this->getValidationMessages('CriticalTerm')
            );

            // Check if at least one field is provided
            if (empty($validatedData)) {
                return $this->errorResponse('At least one field must be provided for update', 'NO_FIELDS_PROVIDED', 422);
            }

            // Find the Critical Term by ID
            $criticalTerm = CriticalTerm::findOrFail($id);

            if (isset($validatedData['name']) && $validatedData['name'] !== $criticalTerm->name) {
                $existingName = CriticalTerm::where('name', $validatedData['name'])->first();

                if ($existingName) {
                    return $this->errorResponse('Critical Term already exists', 'CRITICAL_TERM_EXISTS', 409);
                }
            }

            // Update the Critical Term
            $criticalTerm->update([
                ...$validatedData,
                'created_by_role' => $request->user()->role,
                'created_by_user_id' => $request->user()->id
            ]);

            $this->forgetCacheByModelType('App\Models\CriticalTerm');

            return $this->successResponse($criticalTerm, 'Critical Term updated successfully', 200);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Critical Term not found', 'NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Delete Critical Term
     * 
     * Endpoint: DELETE /critical-terms/{id}
     *
     * Permanently removes a critical term from the system.
     * This action cannot be undone.
     * 
     * @group CriticalTerms
     * 
     * @urlParam id required integer The ID of the critical term to delete. Example: 3
     * 
     * @response status=200 scenario="Critical Term deleted" {
     *   "status": "success",
     *   "message": "Critical Term 'mild_term' in language 'de' with severity '1' deleted successfully",
     *   "code": 200,
     *   "count": 0,
     *   "data": null
     * }
     *
     * @response status=403 scenario="Unauthorized" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     *
     * @response status=404 scenario="Critical Term not found" {
     *   "status": "error",
     *   "message": "Critical Term not found",
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
    public function destroy(string $id) {
        try {
            $this->authorize('delete', CriticalTerm::class);

            $criticalTerm = CriticalTerm::findOrFail($id);

            // Get the name, language, and severity of the Critical Term
            $name = $criticalTerm->name;
            $language = $criticalTerm->language;
            $severity = $criticalTerm->severity;

            // Delete the Critical Term
            $criticalTerm->delete();

            $this->forgetCacheByModelType('App\Models\CriticalTerm');

            return $this->successResponse(null, "Critical Term '$name' in language '$language' with severity '$severity' deleted successfully", 200);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Critical Term not found', 'NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
