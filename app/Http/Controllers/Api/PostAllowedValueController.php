<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\PostAllowedValue;

use App\Traits\ApiResponses; // example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\ApiSorting;  // example $query = $this->sort(request(), $query, ['id', 'title', 'language', 'category', 'status']);
use App\Traits\ApiFiltering; // example $query = $this->filter(request(), $query, ['title', 'language', 'category', 'status']);
use App\Traits\ApiSelectable; // example $this->selectAttributes($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiPagination; // example $this->getPerPage($request, $query, 10);
use App\Traits\ApiInclude; // example $this->checkForIncludedRelations($request, $query);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Illuminate\Auth\Access\AuthorizationException;


class PostAllowedValueController extends Controller {

    /**
     *  The traits used in the controller
     */
    use AuthorizesRequests, ApiResponses, ApiSorting, ApiFiltering, ApiSelectable, ApiPagination, ApiInclude, QueryBuilder;

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
     *  Check if the Post Allowed Value is used in any posts
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
     * Display a listing of the resource.
     */
    public function index(Request $request) {
        try {
            if (PostAllowedValue::count() == 0) {
                return $this->errorResponse('No Post Allowed Values found', 'NOT_FOUND', 404);
            }

            // Check if the user is authorized to view Post Allowed Values
            $this->authorize('viewAny', PostAllowedValue::class);

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
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
        try {
            // Check if the user is authorized to create Post Allowed Values
            $this->authorize('create', PostAllowedValue::class);

            $validatedData = $request->validate(
                $this->getValidationRulesCreate(),
                $this->getValidationMessages()
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

            return $this->successResponse($postAllowedValue, 'Post Allowed Value created successfully', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id) {
        try {
            // Check if the user is authorized to view Post Allowed Values
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
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id) {
        try {
            // Check if the user is authorized to update Post Allowed Values
            $this->authorize('update', PostAllowedValue::class);

            $validatedData = $request->validate(
                $this->getValidationRulesUpdate(),
                $this->getValidationMessages()
            );

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

            return $this->successResponse($postAllowedValue, 'Post Allowed Value updated successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Post Allowed Value not found', 'POST_ALLOWED_VALUE_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id) {
        try {
            // Check if the user is authorized to delete Post Allowed Values
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

            return $this->successResponse(null, "Post Allowed Value: $name with type: $type deleted successfully", 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Post Allowed Value not found', 'POST_ALLOWED_VALUE_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
