<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\ForbiddenName;

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

class ForbiddenNameController extends Controller {

    /**
     *  The traits used in the controller
     */
    use AuthorizesRequests, ApiResponses, ApiSorting, ApiFiltering, ApiSelectable, ApiPagination, ApiInclude, QueryBuilder;

    /**
     * The validation rules for the create method
     */
    public function getValidationRulesCreate(): array {
        $validationRulesUpdate = [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'match_type' => ['required', 'string', 'in:exact,partial'],
        ];
        return $validationRulesUpdate;
    }


    /**
     * The validation rules for the Update method
     */
    public function getValidationRulesUpdate(): array {
        $validationRulesUpdate = [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'match_type' => ['sometimes', 'required', 'string', 'in:exact,partial'],
        ];
        return $validationRulesUpdate;
    }


    /**
     * Display a listing of the resource.
     */
    public function index(Request $request) {
        try {
            if (ForbiddenName::count() == 0) {
                return $this->errorResponse('No forbidden names found', 'NOT_FOUND', 404);
            }

            // Check if the user is authorized to view forbidden names
            $this->authorize('viewAny', ForbiddenName::class);

            $query = ForbiddenName::query();

            $query = $this->buildQuery($request, $query, 'forbiddenName');

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
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
        try {
            // Check if the user is authorized to create forbidden names
            $this->authorize('create', ForbiddenName::class);

            $validatedData = $request->validate(
                $this->getValidationRulesCreate(),
                $this->getValidationMessages()
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

            return $this->successResponse($forbiddenName, 'Forbidden name created successfully', 201);
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
            // Check if the user is authorized to view forbidden names
            $this->authorize('view', ForbiddenName::class);

            $query = ForbiddenName::query()->where('id', $id);

            $query = $this->buildQuerySelect($request, $query, 'forbiddenName');

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
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {
        try {
            // Check if the user is authorized to update forbidden names
            $this->authorize('update', ForbiddenName::class);

            $validatedData = $request->validate(
                $this->getValidationRulesUpdate(),
                $this->getValidationMessages()
            );

            // Find the forbidden name by ID
            $forbiddenName = ForbiddenName::findOrFail($id);

            // Check if the forbidden name already exists
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

            return $this->successResponse($forbiddenName, 'Forbidden name updated successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Forbidden name not found', 'NOT_FOUND', 404);
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
    public function destroy($id) {
        try {
            // Check if the user is authorized to delete forbidden names
            $this->authorize('delete', ForbiddenName::class);

            // Find the forbidden name by ID
            $forbiddenName = ForbiddenName::findOrFail($id);

            // Get the name of the forbidden name
            $name = $forbiddenName->name;

            // Delete the forbidden name
            $forbiddenName->delete();

            return $this->successResponse(null, "Forbidden name: $name deleted successfully", 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Forbidden name not found', 'NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
