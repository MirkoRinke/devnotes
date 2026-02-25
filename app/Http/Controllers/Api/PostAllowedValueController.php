<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use App\Models\Post;
use App\Models\PostAllowedValue;
use App\Models\PostTag;
use App\Traits\ApiResponses;
use App\Traits\QueryBuilder;
use App\Traits\ApiInclude;
use App\Traits\RelationLoader;
use App\Traits\CacheHelper;
use App\Traits\FieldManager;
use App\Traits\PostAllowedValueHelper;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Illuminate\Auth\Access\AuthorizationException;


/**
 * PostAllowedValueController
 * 
 * This controller handles the CRUD operations for Post Allowed Values.
 * It allows admin users to create, read, update, and delete allowed values
 * that can be used in posts.
 */
class PostAllowedValueController extends Controller {

    /**
     *  The traits used in the controller
     */
    use AuthorizesRequests, ApiResponses, ApiInclude, QueryBuilder, CacheHelper, RelationLoader, FieldManager, PostAllowedValueHelper;

    /**
     * The validation rules for the create method
     * 
     * @return array 
     * 
     * @example | $this->getValidationRulesCreate()
     */
    public function getValidationRulesCreate(): array {
        $validationRulesCreate = [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'type' => ['required', 'string', 'in:language,category,post_type,technology,status,tag'],
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
            'name' => ['sometimes', 'required', 'string', 'min:1', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'in:language,category,post_type,technology,status,tag'],
        ];
        return $validationRulesUpdate;
    }

    /**
     * Setup the query for the Post Allowed Values
     * This method is used to setup the query for the Post Allowed Values
     * It applies sorting, filtering, selecting, and pagination
     * It also loads the relations for the Post Allowed Values
     * 
     * @param Request $request
     * @param mixed $query Builder|LengthAwarePaginator|Collection
     * @param string $methods The method to call for building the query
     * @return mixed Builder|LengthAwarePaginator|Collection
     * 
     * @example | $this->setupPostAllowedValueQuery($request, $query, 'buildQuery')
     */
    protected function setupPostAllowedValueQuery(Request $request, $query, $methods): mixed {
        $relationKeyFields = $this->getRelationKeyFields($request, ['user' => 'created_by_user_id']);

        $this->modifyRequestSelect($request, [...['id'], ...$relationKeyFields]);

        $this->loadUserRelation($request, $query, 'created_by_user_id');

        $query = $this->$methods($request, $query, 'post_allowed_values');
        if ($query instanceof JsonResponse) {
            return $query;
        }

        return $query;
    }

    /**
     * List All Post Allowed Values
     * 
     * Endpoint: GET /post-allowed-values
     *
     * Only users with the roles **admin** or **moderator** can access this endpoint.
     *
     * Retrieves a list of allowed values that can be used in posts, with support for filtering, sorting, field selection, relation inclusion, and pagination.
     * **By default, results are paginated.**
     *
     * You can use the `*_fields` parameter for all relations (e.g. `user_fields`) to specify which fields should be returned for each relation.
     * 
     * @group PostAllowedValue
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
     * Example URL: /post-allowed-values
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Post Allowed Values retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "HTML",
     *       "type": "language",
     *       "post_count": 106,
     *       "created_by_role": "system",
     *       "created_by_user_id": 2,
     *       "created_at": "2025-07-05T21:38:59.000000Z",
     *       "updated_at": "2025-07-05T21:38:59.000000Z"
     *     }
     *   ]
     * }
     *
     * Example URL: /post-allowed-values/?include=user
     * 
     * @response status=200 scenario="Success with user relation" {
     *   "status": "success",
     *   "message": "Post Allowed Values retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "HTML",
     *       "type": "language",
     *       "post_count": 106,
     *       "created_by_role": "system",
     *       "created_by_user_id": 2,
     *       "created_at": "2025-07-05T21:38:59.000000Z",
     *       "updated_at": "2025-07-05T21:38:59.000000Z",
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
     * @response status=200 scenario="No Post Allowed Values found" {
     *   "status": "success",
     *   "message": "No Post Allowed Values found",
     *   "code": 200,
     *   "count": 0,
     *   "data": []
     * }
     *
     * @response status=404 scenario="No values exist" {
     *   "status": "error",
     *   "message": "No Post Allowed Values found",
     *   "code": 404,
     *   "errors": "NOT_FOUND"
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
    public function index(Request $request) {
        try {
            $this->authorize('viewAny', PostAllowedValue::class);

            if (PostAllowedValue::count() == 0) {
                return $this->errorResponse('No Post Allowed Values found', 'NOT_FOUND', 404);
            }

            $query = PostAllowedValue::query();

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupPostAllowedValueQuery($request, $query, 'buildQuery');
            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No Post Allowed Values found', 200);
            }

            $query = $this->moderationFieldsVisibilityRelation($request, $query);

            $query = $this->checkForIncludedRelations($request, $query);

            $query = $this->controlVisibleFields($request, $originalSelectFields, $query);

            return $this->successResponse($query, 'Post Allowed Values retrieved successfully', 200);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Create a New Post Allowed Value
     * 
     * Endpoint: POST /post-allowed-values
     *
     * Creates a new allowed value that can be used in posts.
     * Only users with the roles **admin** or **moderator** can access this endpoint.
     *
     * @group PostAllowedValue
     *
     * @bodyParam name string required The name of the allowed value. Example: Assembler
     * @bodyParam type string required The type of the allowed value. Must be one of: language, category, post_type, technology, status, tag. Example: language
     * 
     * @bodyContent {
     *   "name": "Assembler",                  || required, string, min:1, max:255
     *   "type": "language"                    || required, string, must be one of: language, category, post_type, technology, status, tag
     * }
     * 
     * @response status=201 scenario="Success" {
     *   "status": "success",
     *   "message": "Post Allowed Value created successfully",
     *   "code": 201,
     *   "count": 1,
     *   "data": {
     *     "name": "Assembler",
     *     "type": "language",
     *     "created_by_role": "admin",
     *     "created_by_user_id": 1,
     *     "updated_at": "2025-07-08T20:52:38.000000Z",
     *     "created_at": "2025-07-08T20:52:38.000000Z",
     *     "id": 70
     *   }
     * }
     *
     * @response status=409 scenario="Duplicate Entry" {
     *   "status": "error",
     *   "message": "Post Allowed Value already exists",
     *   "code": 409,
     *   "errors": "POST_ALLOWED_VALUE_EXISTS"
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
     *     "type": ["TYPE_FIELD_REQUIRED"]
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
            $this->authorize('create', PostAllowedValue::class);

            $user = $request->user();

            $validatedData = $request->validate(
                $this->getValidationRulesCreate(),
                $this->getValidationMessages('PostAllowedValue')
            );

            $validatedData['name'] = trim($validatedData['name']);

            $existingPostAllowedValue = PostAllowedValue::whereRaw('LOWER(name) = LOWER(?) AND type = ?', [$validatedData['name'], $validatedData['type']])->first();

            $convertToLower = in_array($validatedData['type'], ['category', 'post_type', 'status']);
            if ($convertToLower) {
                $validatedData['name'] = strtolower($validatedData['name']);
            }

            $formatValueByType = in_array($validatedData['type'], ['language', 'technology', 'tag']);
            if ($formatValueByType) {
                $validatedData['name'] = $this->formatValueByType($validatedData['type'], $validatedData['name'], false);
            }


            if ($existingPostAllowedValue) {
                return $this->errorResponse('Post Allowed Value already exists', 'POST_ALLOWED_VALUE_EXISTS', 409);
            }

            $postAllowedValue = new PostAllowedValue($validatedData);
            $postAllowedValue->created_by_role = $user->role;
            $postAllowedValue->created_by_user_id = $user->id;
            $postAllowedValue->save();

            $this->forgetPostAllowedValueCache($postAllowedValue->type);

            return $this->successResponse($postAllowedValue, 'Post Allowed Value created successfully', 201);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Get a Specific Post Allowed Value
     * 
     * Endpoint: GET /post-allowed-values/{id}
     *
     * Retrieves a specific post allowed value by its ID.
     * 
     * Only users with the roles **admin** or **moderator** can access this endpoint.
     * Returns complete details of the requested value that can be used in posts.
     *
     * @group PostAllowedValue
     *
     * @urlParam id integer required The ID of the post allowed value. Example: 1
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
     * Example URL: /post-allowed-values/1
     *
     * @response status=200 scenario="Success without user relation" {
     *   "status": "success",
     *   "message": "Post Allowed Value retrieved successfully",
     *   "code": 200,
     *   "data": {
     *     "id": 1,
     *     "name": "HTML",
     *     "type": "language",
     *     "post_count": 106,
     *     "created_by_role": "system",
     *     "created_by_user_id": 2,
     *     "created_at": "2025-07-05T21:38:59.000000Z",
     *     "updated_at": "2025-07-05T21:38:59.000000Z"
     *   }
     * }
     *
     * Example URL: /post-allowed-values/1/?include=user
     *
     * @response status=200 scenario="Success with user relation" {
     *   "status": "success",
     *   "message": "Post Allowed Value retrieved successfully",
     *   "code": 200,
     *   "data": {
     *     "id": 1,
     *     "name": "HTML",
     *     "type": "language",
     *     "post_count": 106,
     *     "created_by_role": "system",
     *     "created_by_user_id": 2,
     *     "created_at": "2025-07-05T21:38:59.000000Z",
     *     "updated_at": "2025-07-05T21:38:59.000000Z",
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
     * @response status=403 scenario="Unauthorized" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     *
     * @response status=404 scenario="Not Found" {
     *   "status": "error",
     *   "message": "Post Allowed Value not found",
     *   "code": 404,
     *   "errors": "POST_ALLOWED_VALUE_NOT_FOUND"
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
            $this->authorize('view', PostAllowedValue::class);

            $query = PostAllowedValue::query()->where('id', $id);

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupPostAllowedValueQuery($request, $query, 'buildQuerySelect');
            if ($query instanceof JsonResponse) {
                return $query;
            }

            $query = $query->firstOrFail();

            $query = $this->moderationFieldsVisibilityRelation($request, $query);

            $query = $this->checkForIncludedRelations($request, $query);

            $query = $this->controlVisibleFields($request, $originalSelectFields, $query);

            return $this->successResponse($query, 'Post Allowed Value retrieved successfully', 200);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Post Allowed Value not found', 'POST_ALLOWED_VALUE_NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Update a Post Allowed Value
     * 
     * Endpoint: PATCH /post-allowed-values/{id}
     *
     * Updates an existing post allowed value. Only users with the roles **admin** or **moderator** can update values.
     * 
     * At least one of the fields (`name`, `type`) must be provided.
     * Changing the `type` is only allowed if the value is not currently in use.
     *
     * @group PostAllowedValue
     *
     * @urlParam id integer required The ID of the post allowed value to update. Example: 70
     * @bodyParam name string The new name for the allowed value. Example: Assembler
     * @bodyParam type string The new type for the allowed value. Must be one of: language, category, post_type, technology, status, tag. Example: language
     * 
     * @bodyContent {
     *   "name": "Assembler",           || optional, string, min:1, max:255
     *   "type": "language"             || optional, string, must be one of: language, category, post_type, technology, status, tag
     * }
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Post Allowed Value updated successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 70,
     *     "name": "Assembler",
     *     "type": "language",
     *     "created_by_role": "admin",
     *     "created_by_user_id": 1,
     *     "created_at": "2025-07-08T20:52:38.000000Z",
     *     "updated_at": "2025-07-08T21:45:08.000000Z"
     *   }
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
     *     "name": ["NAME_MUST_BE_STRING"],
     *     "type": ["TYPE_INVALID_OPTION"]
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
     * @response status=409 scenario="Duplicate Entry" {
     *   "status": "error",
     *   "message": "Post Allowed Value already exists",
     *   "code": 409,
     *   "errors": "POST_ALLOWED_VALUE_EXISTS"
     * }
     *
     * @response status=409 scenario="Value in Use" {
     *   "status": "error",
     *   "message": "Post Allowed Value 'Assembler' is used in posts and its type cannot be changed",
     *   "code": 409,
     *   "errors": "POST_ALLOWED_VALUE_IN_USE"
     * }
     *
     * @response status=404 scenario="Not Found" {
     *   "status": "error",
     *   "message": "Post Allowed Value not found",
     *   "code": 404,
     *   "errors": "POST_ALLOWED_VALUE_NOT_FOUND"
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
            $this->authorize('update', PostAllowedValue::class);

            $user = $request->user();

            $validatedData = $request->validate(
                $this->getValidationRulesUpdate(),
                $this->getValidationMessages('PostAllowedValue')
            );

            $postAllowedValue = PostAllowedValue::findOrFail($id);

            $validatedData['name'] = trim($validatedData['name']);

            /**
             * If type is provided, check if the Post Allowed Value is in use
             */
            if (isset($validatedData['type']) && $validatedData['type'] !== $postAllowedValue->type) {
                $isInUse = $this->isPostAllowedValueInUse($postAllowedValue->name, $postAllowedValue->type, $id);
                if ($isInUse) {
                    return $this->errorResponse("Post Allowed Value '{$postAllowedValue->name}' is used in posts and its type cannot be changed", 'POST_ALLOWED_VALUE_IN_USE', 409);
                }
            }

            if (empty($validatedData)) {
                return $this->errorResponse('At least one field must be provided for update', 'NO_FIELDS_PROVIDED', 422);
            }

            $nameToCheck = $validatedData['name'] ?? $postAllowedValue->name;
            $typeToCheck = $validatedData['type'] ?? $postAllowedValue->type;

            $existingPostAllowedValue = PostAllowedValue::whereRaw('LOWER(name) = LOWER(?) AND type = ? AND id != ?', [$nameToCheck, $typeToCheck, $id])->first();

            $convertToLower = in_array($validatedData['type'], ['category', 'post_type', 'status']);
            if ($convertToLower) {
                $validatedData['name'] = strtolower($validatedData['name']);
            }

            $formatValueByType = in_array($validatedData['type'], ['language', 'technology', 'tag']);
            if ($formatValueByType) {
                $validatedData['name'] = $this->formatValueByType($validatedData['type'], $validatedData['name'], false);
            }

            if ($existingPostAllowedValue) {
                return $this->errorResponse('Post Allowed Value already exists', 'POST_ALLOWED_VALUE_EXISTS', 409);
            }

            /**
             * Note: We update the created_by fields because each change basically redefines the entire entity
             */
            $postAllowedValue->fill($validatedData);
            $postAllowedValue->created_by_role = $user->role;
            $postAllowedValue->created_by_user_id = $user->id;
            $postAllowedValue->save();

            $this->forgetPostAllowedValueCache($postAllowedValue->type);

            return $this->successResponse($postAllowedValue, 'Post Allowed Value updated successfully', 200);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Post Allowed Value not found', 'POST_ALLOWED_VALUE_NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Delete a Post Allowed Value
     * 
     * Endpoint: DELETE /post-allowed-values/{id}
     *
     * Permanently removes a post allowed value from the system.
     * A value cannot be deleted if it is currently in use by any posts.
     * 
     * Only users with the roles **admin** or **moderator** can delete values.
     *
     * @group PostAllowedValue
     *
     * @urlParam id integer required The ID of the post allowed value to delete. Example: 70
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Post Allowed Value: Assembler with type: language deleted successfully",
     *   "code": 200,
     *   "count": 0,
     *   "data": null
     * }
     *
     * @response status=409 scenario="Value in Use" {
     *   "status": "error",
     *   "message": "Post Allowed Value 'Assembler' is used in posts and cannot be deleted",
     *   "code": 409,
     *   "errors": "POST_ALLOWED_VALUE_IN_USE"
     * }
     *
     * @response status=403 scenario="Unauthorized" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     *
     * @response status=404 scenario="Not Found" {
     *   "status": "error",
     *   "message": "Post Allowed Value not found",
     *   "code": 404,
     *   "errors": "POST_ALLOWED_VALUE_NOT_FOUND"
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
            $this->authorize('delete', PostAllowedValue::class);

            $postAllowedValue = PostAllowedValue::findOrFail($id);

            $name = $postAllowedValue->name;
            $type = $postAllowedValue->type;

            $isInUse = $this->isPostAllowedValueInUse($name, $type);

            if ($isInUse) {
                return $this->errorResponse("Post Allowed Value '$name' is used in posts and cannot be deleted", 'POST_ALLOWED_VALUE_IN_USE', 409);
            }

            $postAllowedValue->delete();

            $this->forgetPostAllowedValueCache($postAllowedValue->type);

            return $this->successResponse(null, "Post Allowed Value: $name with type: $type deleted successfully", 200);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Post Allowed Value not found', 'POST_ALLOWED_VALUE_NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
