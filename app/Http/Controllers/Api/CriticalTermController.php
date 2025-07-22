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

        $this->loadUserRelation($request, $query, 'created_by_user_id');

        $query = $this->$methods($request, $query, 'critical_terms');
        if ($query instanceof JsonResponse) {
            return $query;
        }

        return $query;
    }


    /**
     * List All Critical Terms
     * 
     * Endpoint: GET /critical-terms
     * 
     * Only users with the roles **admin**, or **moderator**, can access these endpoints.
     *
     * Retrieves a list of critical terms with support for filtering, sorting, field selection, relation inclusion, and pagination.  
     * **By default, results are paginated.**
     *
     * You can use the `*_fields` parameter for all relations (e.g. `user_fields`) to specify which fields should be returned for each relation.
     * 
     * @group CriticalTerms
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
     * Example URL: /critical-terms
     * 
     * @response status=200 scenario="Success without user relation" {
     *   "status": "success",
     *   "message": "Critical Terms retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "fraud",
     *       "language": "en",
     *       "severity": 3,
     *       "created_by_role": "system",
     *       "created_by_user_id": 2,
     *       "created_at": "2025-07-05T21:40:26.000000Z",
     *       "updated_at": "2025-07-05T21:40:26.000000Z"
     *     }
     *   ]
     * }
     *
     * Example URL: /critical-terms/?include=user
     * 
     * @response status=200 scenario="Success with user relation" {
     *   "status": "success",
     *   "message": "Critical Terms retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "fraud",
     *       "language": "en",
     *       "severity": 3,
     *       "created_by_role": "system",
     *       "created_by_user_id": 2,
     *       "created_at": "2025-07-05T21:40:26.000000Z",
     *       "updated_at": "2025-07-05T21:40:26.000000Z",
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
     * Only users with the roles **admin** or **moderator** can access this endpoint.
     * 
     * @group CriticalTerms
     * 
     * @bodyParam name string required The name of the critical term. Min: 2, Max: 255. Example: phishing
     * @bodyParam language string required The language code of the term. Min: 2, Max: 255. Example: en
     * @bodyParam severity integer required The severity level of the term (1-5). Example: 4
     * 
     * @bodyContent {
     *   "name": "phishing",      || required, string, min:2, max:255
     *   "language": "en",        || required, string, min:2, max:255
     *   "severity": 4            || required, integer, must be between 1 and 5
     * }
     *
     * @response status=201 scenario="Critical Term created" {
     *   "status": "success",
     *   "message": "Critical Term created successfully",
     *   "code": 201,
     *   "count": 1,
     *   "data": {
     *     "id": 3,
     *     "name": "phishing",
     *     "language": "en",
     *     "severity": 4,
     *     "created_by_role": "admin",
     *     "created_by_user_id": 1,
     *     "created_at": "2025-07-06T18:14:01.000000Z",
     *     "updated_at": "2025-07-06T18:14:01.000000Z"
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
            $this->authorize('create', CriticalTerm::class);

            $user = $request->user();

            $validatedData = $request->validate(
                $this->getValidationRulesCreate(),
                $this->getValidationMessages('CriticalTerm')
            );

            $existingCriticalTerm = CriticalTerm::where('name', $validatedData['name'])->first();

            if ($existingCriticalTerm) {
                return $this->errorResponse('Critical Term already exists', 'CRITICAL_TERM_EXISTS', 409);
            }

            $criticalTerm = new CriticalTerm($validatedData);
            $criticalTerm->created_by_role = $user->role;
            $criticalTerm->created_by_user_id = $user->id;
            $criticalTerm->save();

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
     * Only users with the roles **admin** or **moderator** can access this endpoint.
     * 
     * @group CriticalTerms
     * 
     * @urlParam id integer required The ID of the critical term. Example: 2
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
     * Example URL: /critical-terms/2
     *
     * @response status=200 scenario="Success without user relation" {
     *   "status": "success",
     *   "message": "Critical Term retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 2,
     *     "name": "malware",
     *     "language": "en",
     *     "severity": 4,
     *     "created_by_role": "system",
     *     "created_by_user_id": 2,
     *     "created_at": "2025-07-05T21:40:26.000000Z",
     *     "updated_at": "2025-07-05T21:40:26.000000Z"
     *   }
     * }
     * 
     * Example URL: /critical-terms/2/?include=user
     *
     * @response status=200 scenario="Success with user relation" {
     *   "status": "success",
     *   "message": "Critical Term retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 2,
     *     "name": "malware",
     *     "language": "en",
     *     "severity": 4,
     *     "created_by_role": "system",
     *     "created_by_user_id": 2,
     *     "created_at": "2025-07-05T21:40:26.000000Z",
     *     "updated_at": "2025-07-05T21:40:26.000000Z",
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

            /**
             * Need this because the buildQuerySelect method returns only the query object
             */
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
     * Only users with the roles **admin** or **moderator** can access this endpoint.
     * 
     * @group CriticalTerms
     * 
     * @urlParam id integer required The ID of the critical term to update. Example: 4
     *
     * @bodyParam name string optional The name of the critical term. Min: 2, Max: 255. Example: malware
     * @bodyParam language string optional The language code of the term. Min: 2, Max: 255. Example: en
     * @bodyParam severity integer optional The severity level of the term (1-5). Example: 4
     * 
     * @bodyContent {
     *   "name": "malware",      || optional, string, min:2, max:255, at least one field required
     *   "language": "en",       || optional, string, min:2, max:255, at least one field required
     *   "severity": 4           || optional, integer, must be between 1 and 5, at least one field required
     * }
     *
     * @response status=200 scenario="Critical Term updated" {
     *   "status": "success",
     *   "message": "Critical Term updated successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 4,
     *     "name": "malware",
     *     "language": "en",
     *     "severity": 4,
     *     "created_by_role": "admin",
     *     "created_by_user_id": 1,
     *     "created_at": "2025-07-05T21:40:26.000000Z",
     *     "updated_at": "2025-07-06T18:23:52.000000Z"
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
            $this->authorize('update', CriticalTerm::class);

            $user = $request->user();

            $validatedData = $request->validate(
                $this->getValidationRulesUpdate(),
                $this->getValidationMessages('CriticalTerm')
            );

            if (empty($validatedData)) {
                return $this->errorResponse('At least one field must be provided for update', 'NO_FIELDS_PROVIDED', 422);
            }

            $criticalTerm = CriticalTerm::findOrFail($id);

            if (isset($validatedData['name']) && $validatedData['name'] !== $criticalTerm->name) {
                $existingName = CriticalTerm::where('name', $validatedData['name'])->first();

                if ($existingName) {
                    return $this->errorResponse('Critical Term already exists', 'CRITICAL_TERM_EXISTS', 409);
                }
            }

            $criticalTerm->fill($validatedData);
            $criticalTerm->created_by_role = $user->role;
            $criticalTerm->created_by_user_id = $user->id;
            $criticalTerm->save();

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
     * Only users with the roles **admin** or **moderator** can access this endpoint.
     * 
     * @group CriticalTerms
     * 
     * @urlParam id integer required The ID of the critical term to delete. Example: 3
     * 
     * @response status=200 scenario="Critical Term deleted" {
     *   "status": "success",
     *   "message": "Critical Term 'phishing' in language 'en' with severity '4' deleted successfully",
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

            $name = $criticalTerm->name;
            $language = $criticalTerm->language;
            $severity = $criticalTerm->severity;

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
