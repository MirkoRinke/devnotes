<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use App\Models\Post;
use App\Models\PostAllowedValue;

use App\Traits\ApiResponses; // example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\ApiInclude; // example $this->checkForIncludedRelations($request, $query);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);
use App\Traits\CacheHelper; // example $this->forgetCacheByModelType('App\Models\Post');

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Illuminate\Auth\Access\AuthorizationException;


class PostAllowedValueController extends Controller {

    /**
     *  The traits used in the controller
     */
    use AuthorizesRequests, ApiResponses, ApiInclude, QueryBuilder, CacheHelper;

    /**
     * The validation rules for the create method
     */
    public function getValidationRulesCreate(): array {
        $validationRulesUpdate = [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'type' => ['required', 'string', 'in:language,category,post_type,technology,status'],
        ];
        return $validationRulesUpdate;
    }

    /**
     * The validation rules for the Update method
     */
    public function getValidationRulesUpdate(): array {
        $validationRulesUpdate = [
            'name' => ['sometimes', 'required', 'string', 'min:1', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'in:language,category,post_type,technology,status'],
        ];
        return $validationRulesUpdate;
    }


    /**
     * Check if the Post Allowed Value is used in any posts
     * 
     * @param string $name The name of the allowed value
     * @param string $type The type of the allowed value (language, category, post_type, technology, status)
     * @return bool True if the value is in use, false otherwise
     */
    protected function isPostAllowedValueInUse($name, $type) {
        $isInUse = false;

        switch ($type) {
            case 'category':
                $isInUse = Post::where('category', $name)->exists();
                break;
            case 'post_type':
                $isInUse = Post::where('post_type', $name)->exists();
                break;
            case 'status':
                $isInUse = Post::where('status', $name)->exists();
                break;
            case 'language':
                $isInUse = Post::whereJsonContains('language', $name)->exists();
                break;
            case 'technology':
                $isInUse = Post::whereJsonContains('technology', $name)->exists();
                break;
        }
        return $isInUse;
    }

    /**
     * List All Post Allowed Values
     * 
     * Endpoint: GET /post-allowed-values
     *
     * Retrieves a paginated list of all allowed values that can be used in posts.
     * These values define the acceptable options for post languages, categories, types,
     * technologies, and status settings.
     *
     * Results can be filtered, sorted, and paginated using query parameters.
     *
     * @group PostAllowedValue
     *
     * @queryParam filter[type] string Filter by value type (language, category, post_type, technology, status). Example: language
     * @queryParam filter[name] string Filter by value name. Example: PHP
     * @queryParam filter[created_by_role] string Filter by creator role (admin, moderator, user). Example: admin
     * @queryParam sort string Sort by field. Prefix with - for descending order. Example: -created_at
     * @queryParam startsWith string Filter results to only those where specified field starts with given string. Format: field:value. Example: name:P
     * @queryParam endsWith string Filter results to only those where specified field ends with given string. Format: field:value. Example: name:Script
     * 
     * @queryParam page number The page number. Example: 1
     * @queryParam per_page number Number of items per page. Example: 15
     * 
     * Example URL: /post-allowed-values
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Post Allowed Values retrieved successfully",
     *   "code": 200,
     *   "count": 2,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "PHP",
     *       "type": "language",
     *       "created_by_role": "admin",
     *       "created_by_user_id": 1,
     *       "created_at": "2023-09-15T14:25:10.000000Z",
     *       "updated_at": "2023-09-15T14:25:10.000000Z"
     *     },
     *     {
     *       "id": 2,
     *       "name": "JavaScript",
     *       "type": "language",
     *       "created_by_role": "admin",
     *       "created_by_user_id": 1,
     *       "created_at": "2023-09-15T14:25:32.000000Z",
     *       "updated_at": "2023-09-15T14:25:32.000000Z"
     *     }
     *   ],
     * }
     * 
     * Example URL: /post-allowed-values/?select=id,name,type
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Post Allowed Values retrieved successfully",
     *   "code": 200,
     *   "count": 2,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "PHP",
     *       "type": "language",
     *     },
     *     {
     *       "id": 2,
     *       "name": "JavaScript",
     *       "type": "language",
     *     }
     *   ],
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

            $query = $this->buildQuery($request, $query, 'post_allowed_values');

            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No Post Allowed Values found', 200);
            }

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
     * This endpoint requires admin privileges.
     *
     * @group PostAllowedValue
     *
     * @bodyParam name string required The name of the allowed value. Example: Rust
     * @bodyParam type string required The type of the allowed value (language, category, post_type, technology, status). Example: language
     * 
     * @bodyContent {
     *   "name": "Rust",                  // required, string, min:1, max:255
     *   "type": "language"               // required, string, must be one of: language, category, post_type, technology, status
     * }
     * 
     * @response status=201 scenario="Success" {
     *   "status": "success",
     *   "message": "Post Allowed Value created successfully",
     *   "code": 201,
     *   "data": {
     *     "id": 3,
     *     "name": "Rust",
     *     "type": "language",
     *     "created_by_role": "admin",
     *     "created_by_user_id": 1,
     *     "created_at": "2023-09-15T15:42:10.000000Z",
     *     "updated_at": "2023-09-15T15:42:10.000000Z"
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

            $validatedData = $request->validate(
                $this->getValidationRulesCreate(),
                $this->getValidationMessages('PostAllowedValue')
            );

            $existingPostAllowedValue = PostAllowedValue::where('name', $validatedData['name'])
                ->where('type', $validatedData['type'])
                ->first();

            if ($existingPostAllowedValue) {
                return $this->errorResponse('Post Allowed Value already exists', 'POST_ALLOWED_VALUE_EXISTS', 409);
            }

            // Create the Post Allowed Value
            $postAllowedValue = PostAllowedValue::create([
                ...$validatedData,
                'created_by_role' => $request->user()->role,
                'created_by_user_id' => $request->user()->id
            ]);

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
     * Returns complete details of the requested value that can be used in posts.
     *
     * @group PostAllowedValue
     *
     * @urlParam id required The ID of the post allowed value. Example: 1
     * @queryParam select string Comma-separated list of fields to include in the response. Example: id,name,type
     *
     * Example URL: /post-allowed-values/1
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Post Allowed Value retrieved successfully",
     *   "code": 200,
     *   "data": {
     *     "id": 1,
     *     "name": "PHP",
     *     "type": "language",
     *     "created_by_role": "admin",
     *     "created_by_user_id": 1,
     *     "created_at": "2023-09-15T14:25:10.000000Z",
     *     "updated_at": "2023-09-15T14:25:10.000000Z"
     *   }
     * }
     *
     * Example URL with select: /post-allowed-values/1?select=id,name,type
     * 
     * @response status=200 scenario="Success with select parameter" {
     *   "status": "success",
     *   "message": "Post Allowed Value retrieved successfully",
     *   "code": 200,
     *   "data": {
     *     "id": 1,
     *     "name": "PHP",
     *     "type": "language"
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

            $query = $this->buildQuerySelect($request, $query, 'post_allowed_values');

            if ($query instanceof JsonResponse) {
                return $query;
            }

            $postAllowedValue = $query->firstOrFail();

            return $this->successResponse($postAllowedValue, 'Post Allowed Value retrieved successfully', 200);
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
     * Updates an existing post allowed value. This endpoint can be used to change the
     * name and/or type of an allowed value. Only admin users can update values.
     *
     * @group PostAllowedValue
     *
     * @urlParam id required The ID of the post allowed value to update. Example: 1
     * @bodyParam name string The new name for the allowed value. Example: TypeScript
     * @bodyParam type string The new type for the allowed value (language, category, post_type, technology, status). Example: language
     * 
     * @bodyContent {
     *   "name": "TypeScript",           || Optional but at least one field must be provided
     *   "type": "language"              || Optional but at least one field must be provided
     * }
     * 
     * Example URL: /post-allowed-values/1
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Post Allowed Value updated successfully",
     *   "code": 200,
     *   "data": {
     *     "id": 1,
     *     "name": "TypeScript",
     *     "type": "language",
     *     "created_by_role": "admin",
     *     "created_by_user_id": 1,
     *     "created_at": "2023-09-15T14:25:10.000000Z",
     *     "updated_at": "2023-09-15T15:30:22.000000Z"
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

            $validatedData = $request->validate(
                $this->getValidationRulesUpdate(),
                $this->getValidationMessages('PostAllowedValue')
            );

            // Check if at least one field is provided
            if (empty($validatedData)) {
                return $this->errorResponse('At least one field must be provided for update', 'NO_FIELDS_PROVIDED', 422);
            }

            // Find the Post Allowed Value by ID
            $postAllowedValue = PostAllowedValue::findOrFail($id);

            $nameToCheck = $validatedData['name'] ?? $postAllowedValue->name;
            $typeToCheck = $validatedData['type'] ?? $postAllowedValue->type;

            $existingPostAllowedValue = PostAllowedValue::where('name', $nameToCheck)
                ->where('type', $typeToCheck)
                ->where('id', '!=', $id)
                ->first();


            if ($existingPostAllowedValue) {
                return $this->errorResponse('Post Allowed Value already exists', 'POST_ALLOWED_VALUE_EXISTS', 409);
            }

            // Update the Post Allowed Value
            $postAllowedValue->update([
                ...$validatedData,
                'created_by_role' => $request->user()->role,
                'created_by_user_id' => $request->user()->id
            ]);

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
     * Only admin users can delete values.
     *
     * @group PostAllowedValue
     *
     * @urlParam id required The ID of the post allowed value to delete. Example: 3
     *
     * Example URL: /post-allowed-values/3
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Post Allowed Value: Rust with type: language deleted successfully",
     *   "code": 200,
     *   "data": null
     * }
     *
     * @response status=409 scenario="Value in Use" {
     *   "status": "error",
     *   "message": "Post Allowed Value 'PHP' is used in posts and cannot be deleted",
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

            // Find the Post Allowed Value by ID            
            $postAllowedValue = PostAllowedValue::findOrFail($id);

            // Get the name and type of the Post Allowed Value
            $name = $postAllowedValue->name;
            $type = $postAllowedValue->type;

            // Check if the Post Allowed Value is in use
            $isInUse = $this->isPostAllowedValueInUse($name, $type);

            // Prevent deletion if value is in use
            if ($isInUse) {
                return $this->errorResponse("Post Allowed Value '$name' is used in posts and cannot be deleted", 'POST_ALLOWED_VALUE_IN_USE', 409);
            }

            // Delete the Post Allowed Value
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
