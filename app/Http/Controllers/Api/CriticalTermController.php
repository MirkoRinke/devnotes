<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\CriticalTerm;

use App\Traits\ApiResponses; // example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\ApiSorting;  // example $query = $this->sort(request(), $query, ['id', 'title', 'language', 'category', 'status']);
use App\Traits\ApiFiltering; // example $query = $this->filter(request(), $query, ['title', 'language', 'category', 'status']);
use App\Traits\ApiSelectable; // example $this->selectAttributes($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiPagination; // example $this->getPerPage($request, $query, 10);
use App\Traits\ApiInclude; // example $this->checkForIncludedRelations($request, $query);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);
use App\Traits\CacheHelper; // example $this->forgetCacheByModelType('App\Models\CriticalTerm');

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Illuminate\Auth\Access\AuthorizationException;

class CriticalTermController extends Controller {
    /**
     *  The traits used in the controller
     */
    use AuthorizesRequests, ApiResponses, ApiSorting, ApiFiltering, ApiSelectable, ApiPagination, ApiInclude, QueryBuilder, CacheHelper;

    /**
     * The validation rules for the create method
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
     * Display a listing of the resource.
     */
    public function index(Request $request) {
        try {
            if (CriticalTerm::count() == 0) {
                return $this->errorResponse('No Critical Terms found', 'NOT_FOUND', 404);
            }

            // Check if the user is authorized to view Critical Terms
            $this->authorize('viewAny', CriticalTerm::class);

            $query = CriticalTerm::query();

            $query = $this->buildQuery($request, $query, 'critical_terms');

            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No Critical Terms found', 200);
            }

            return $this->successResponse($query, 'Critical Terms retrieved successfully', 200);
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
            // Check if the user is authorized to create Critical Terms
            $this->authorize('create', CriticalTerm::class);

            $validatedData = $request->validate(
                $this->getValidationRulesCreate(),
                $this->getValidationMessages()
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
            // Check if the user is authorized to view Critical Terms
            $this->authorize('view', CriticalTerm::class);

            $query = CriticalTerm::query()->where('id', $id);

            $query = $this->buildQuerySelect($request, $query, 'critical_terms');

            if ($query instanceof JsonResponse) {
                return $query;
            }

            $criticalTerm = $query->firstOrFail();

            return $this->successResponse($criticalTerm, 'Critical Term retrieved successfully', 200);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Critical Term not found', 'NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id) {
        try {
            // Check if the user is authorized to update Critical Terms
            $this->authorize('update', CriticalTerm::class);

            $validatedData = $request->validate(
                $this->getValidationRulesUpdate(),
                $this->getValidationMessages()
            );

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
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Critical Term not found', 'NOT_FOUND', 404);
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
            // Check if the user is authorized to delete Critical Terms
            $this->authorize('delete', CriticalTerm::class);

            // Find the Critical Term by ID
            $criticalTerm = CriticalTerm::findOrFail($id);

            // Get the name, language, and severity of the Critical Term
            $name = $criticalTerm->name;
            $language = $criticalTerm->language;
            $severity = $criticalTerm->severity;

            // Delete the Critical Term
            $criticalTerm->delete();

            $this->forgetCacheByModelType('App\Models\CriticalTerm');

            return $this->successResponse(null, "Critical Term '$name' in language '$language' with severity '$severity' deleted successfully", 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Critical Term not found', 'NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
