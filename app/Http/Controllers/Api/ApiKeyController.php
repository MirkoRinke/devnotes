<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use App\Traits\ApiResponses; // example $this->successResponse($key, 'API key generated successfully', 201);

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Illuminate\Auth\Access\AuthorizationException;

class ApiKeyController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, AuthorizesRequests;

    /**
     * List all API keys
     * 
     * Endpoint: GET /api-keys
     *
     * Retrieves a list of all API keys in the system, excluding the actual key values for security.
     * 
     * Only administrators can access this endpoint.
     *
     * @group API Key Management
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "API-Keys has been retrieved successfully",
     *   "code": 200,
     *   "count": 3,
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Production Frontend",
     *       "active": true,
     *       "created_at": "2025-04-25T10:00:00.000000Z",
     *       "last_used_at": "2025-04-29T15:30:45.000000Z"
     *     },
     *     {
     *       "id": 2,
     *       "name": "Development Environment",
     *       "active": true,
     *       "created_at": "2025-04-24T11:20:00.000000Z", 
     *       "last_used_at": "2025-04-28T09:15:30.000000Z"
     *     },
     *     {
     *       "id": 3,
     *       "name": "Mobile App",
     *       "active": false,
     *       "created_at": "2025-04-20T14:30:00.000000Z",
     *       "last_used_at": null
     *     }
     *   ]
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
            $this->authorize('viewAny', ApiKey::class);

            // Retrieve all API keys from the database 
            $keys = ApiKey::select('id', 'name', 'active', 'created_at', 'last_used_at')
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse($keys, 'API-Keys has been retrieved successfully', 200);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Generate a new API key
     * 
     * Endpoint: POST /api-keys
     *
     * Creates a new API key with the specified name. The actual key value is returned only once
     * upon creation and cannot be retrieved again later for security reasons.
     * 
     * Only administrators can access this endpoint.
     *
     * @group API Key Management
     *
     * @bodyParam name string required The name for the new API key (used for identification). Example: Production Frontend
     *
     * @requestBody {
     *   "name": "Postman API Key"
     * }
     * 
     * @response status=201 scenario="Success" {
     *   "status": "success",
     *   "message": "API key generated successfully",
     *   "code": 201,
     *   "count": 1,
     *   "data": {
     *     "api_key": "7f58dcb6a2d28643e19f28c35eb5245782f8db4d",
     *     "name": "Production Frontend",
     *     "created_at": "2025-04-29T15:30:45.000000Z"
     *   }
     * }
     *
     * @response status=422 scenario="Validation error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "name": ["NAME_FIELD_REQUIRED"]
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
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     *
     * @authenticated
     */
    public function generate(Request $request) {
        try {
            $this->authorize('create', ApiKey::class);

            $validated = $request->validate(
                [
                    'name' => 'required|string|max:255',
                ],
                $this->getValidationMessages('ApiKey')
            );

            // Generate a random 40-character string to be used as the API key
            $key = Str::random(40);

            // Create a new API key record in the database
            $apiKey = ApiKey::create([
                'name' => $validated['name'],
                'key' => $key,
                'active' => true,
            ]);

            return $this->successResponse(['api_key' => $key, 'name' => $apiKey->name, 'created_at' => $apiKey->created_at,], 'API key generated successfully', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Toggle API key status
     * 
     * Endpoint: PATCH /api-keys/{id}/toggle
     *
     * Activates or deactivates an API key. When deactivated, the API key cannot be used to
     * authenticate API requests. This provides a way to temporarily disable access without
     * deleting the key entirely.
     * 
     * Only administrators can access this endpoint.
     *
     * @group API Key Management
     *
     * @urlParam id required The ID of the API key to toggle. Example: 2
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "API key status has been toggled successfully",
     *   "code": 200,
     *   "data": {
     *     "id": 2,
     *     "name": "Development Environment",
     *     "active": false
     *   }
     * }
     *
     * @response status=404 scenario="API key not found" {
     *   "status": "error",
     *   "message": "API key not found",
     *   "code": 404,
     *   "errors": "API_KEY_NOT_FOUND"
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
    public function toggleStatus(Request $request, $id) {
        try {
            $this->authorize('toggleStatus', ApiKey::class);

            $apiKey = ApiKey::findOrFail($id);

            // Toggle the status of the API key (active/inactive) and save the changes to the database
            $apiKey->active = !$apiKey->active;
            $apiKey->save();

            return $this->successResponse(['id' => $apiKey->id, 'name' => $apiKey->name, 'active' => $apiKey->active], 'API key status has been toggled successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('API key not found', 'API_KEY_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Delete an API key
     * 
     * Endpoint: DELETE /api-keys/{id}
     *
     * Permanently removes an API key from the system. Once deleted, the key can no longer 
     * be used for authentication and cannot be recovered. Use this when an API key is no
     * longer needed or may have been compromised.
     *
     * Only administrators can access this endpoint.
     *
     * @group API Key Management
     *
     * @urlParam id required The ID of the API key to delete. Example: 3
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "API-Key 'Mobile App' has been deleted successfully",
     *   "code": 200,
     *   "data": []
     * }
     *
     * @response status=404 scenario="API key not found" {
     *   "status": "error",
     *   "message": "API key not found",
     *   "code": 404,
     *   "errors": "API_KEY_NOT_FOUND"
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
    public function destroy(Request $request, $id) {
        try {
            $this->authorize('delete', ApiKey::class);

            $apiKey = ApiKey::findOrFail($id);

            // Delete the API key from the database
            $name = $apiKey->name;
            $apiKey->delete();

            return $this->successResponse([], "API-Key '$name' has been deleted successfully", 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('API key not found', 'API_KEY_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
