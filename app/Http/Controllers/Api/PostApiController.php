<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Post;
use Illuminate\Http\JsonResponse;

use App\Traits\ApiResponses; // example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\ApiSorting;  // example $query = $this->sort(request(), $query, ['id', 'title', 'language', 'category', 'status']);
use App\Traits\ApiFiltering; // example $query = $this->filter(request(), $query, ['title', 'language', 'category', 'status']);
use App\Traits\SelectableAttributes; // example $this->selectAttributes($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiPagination; // example $this->getPerPage($request, $query, 10);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;


use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PostApiController extends Controller {
    /**
     *  The traits used in the controller
     */
    use ApiResponses, ApiSorting, ApiFiltering, SelectableAttributes, ApiPagination, QueryBuilder, AuthorizesRequests;

    /**
     * The validation rules for the user profile data
     */
    private $validationRules = [
        'title' => 'required|string|max:255',
        'code' => 'required|string',
        'description' => 'required|string',
        'resources' => 'nullable|array',
        'language' => 'required|string|max:50',
        'category' => 'required|string|max:50',
        'tags' => 'required|array',
        'status' => 'required|in:draft,published,archived'
    ];

    /**
     * The methods array contains the methods that are used in the buildQuery method
     */
    private $methods = [
        'sort' => ['id', 'user_id', 'title', 'language', 'category', 'tags', 'status', 'favorite_count'],
        'filter' => ['title', 'user_id', 'language', 'category', 'tags', 'status'],
        'select' => ['id', 'user_id', 'title', 'code', 'description', 'resources', 'language', 'category', 'tags', 'status', 'favorite_count'],
        'getPerPage' => 10
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request) {
        try {
            // Check if the posts table is empty and return a response message            
            if (Post::count() === 0) {
                return $this->successResponse([], 'No posts exist in the database', 200);
            }

            // Get all the posts from the database
            $query = Post::query();

            // Build the query based on the request and return JsonResponse|Collection|LengthAwarePaginator
            $query = $this->buildQuery($request, $query, $this->methods);

            // Check if the query is an instance of JsonResponse and return the response
            if ($query instanceof JsonResponse) {
                return $query;
            }

            // Check if the query is empty after filtering and return the response
            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No posts found with the given filters', 200);
            }

            return $this->successResponse($query, 'Posts retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse('Posts not found', 'POSTS_NOT_FOUND', 404);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse {
        try {
            $validatedData = $request->validate(
                $this->validationRules,
                $this->getValidationMessages()
            );

            $validatedData['tags'] = json_encode($validatedData['tags']);
            $validatedData['resources'] = json_encode($validatedData['resources']);

            $validatedData['user_id'] = $request->user()->id;

            $post = Post::create($validatedData);

            return $this->successResponse($post, 'Post created successfully', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, Request $request): JsonResponse {
        try {
            $query = Post::query()->where('id', $id);

            // Select the user attributes based on the request select array
            $query = $this->select($request, $query, $this->methods['select']);

            // Check return value of the selectAttributes method and return the response if status code is 400
            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            // Need this because the select method returns only the query object
            $post = $query->firstOrFail();

            return $this->successResponse($post, 'Post retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("Post with ID $id does not exist", 'POST_NOT_FOUND', 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse {
        try {
            $post = Post::findOrFail($id);

            // Check if the user can update the post and return a response message
            $this->authorize('update', $post);

            $validatedData = $request->validate(
                $this->validationRules,
                $this->getValidationMessages()
            );

            $validatedData['tags'] = json_encode($validatedData['tags']);
            $validatedData['resources'] = json_encode($validatedData['resources']);

            $post->update($validatedData);

            return $this->successResponse($post, 'Post update successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("Post with ID $id does not exist", 'POST_NOT_FOUND', 404);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('You are not authorized to update this post', 'UNAUTHORIZED_ACTION', 403);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse {
        try {
            $post = Post::findOrFail($id);

            // Check if the user can delete the post and return a response message
            $this->authorize('delete', $post);

            $post->delete();
            return $this->successResponse(null, 'Post deleted successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("Post with ID $id does not exist", 'POST_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('You are not authorized to delete this post', 'UNAUTHORIZED_ACTION', 403);
        }
    }
}
