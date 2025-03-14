<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use App\Traits\ApiResponses;

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
     * Retrieve all API keys
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        try {
            // Check if the authenticated user is authorized to view API keys
            $this->authorize('viewAny', ApiKey::class);

            // Retrieve all API keys from the database 
            $keys = ApiKey::select('id', 'name', 'active', 'created_at', 'last_used_at')
                ->orderBy('created_at', 'desc')
                ->get();

            // Return a success response with the retrieved API keys
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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generate(Request $request) {
        try {
            // Check if the authenticated user is authorized to create API keys
            $this->authorize('create', ApiKey::class);

            // Validate the incoming request data
            $validated = $request->validate([
                'name' => 'required|string|max:255',
            ]);

            // Generate a random 40-character string to be used as the API key
            $key = Str::random(40);

            // Create a new API key record in the database
            $apiKey = ApiKey::create([
                'name' => $validated['name'],
                'key' => $key,
                'active' => true,
            ]);

            // Return a success response with the generated API key
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
     * Toggle the status of an API key
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ApiKey  $apiKey
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStatus(Request $request, $id) {
        try {
            // Check if the authenticated user is authorized to toggle the status of API keys
            $this->authorize('toggleStatus', ApiKey::class);

            // Find the API key in the database using the ID
            $apiKey = ApiKey::findOrFail($id);

            // Toggle the status of the API key (active/inactive) and save the changes to the database
            $apiKey->active = !$apiKey->active;
            $apiKey->save();

            // Return a success response with the updated API key status
            return $this->successResponse(['key_id' => $apiKey->id, 'name' => $apiKey->name, 'active' => $apiKey->active], 'API key status has been toggled successfully', 200);
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
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ApiKey  $apiKey
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id) {
        try {
            // Check if the authenticated user is authorized to delete API keys
            $this->authorize('delete', ApiKey::class);

            // Find the API key in the database using the ID
            $apiKey = ApiKey::findOrFail($id);

            // Delete the API key from the database
            $name = $apiKey->name;
            $apiKey->delete();

            // Return a success response with a message
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
