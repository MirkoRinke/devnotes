<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Post; // Import the Post model to use it in the controller example $posts = Post::all() or Post::findOrFail($id); or Post::create($validatedData); or $post->update($validatedData); or $post->delete(); or Post::query();
use Illuminate\Http\JsonResponse; // Import the JsonResponse class to use it in the controller example return $this->successResponse($posts, 'Posts retrieved successfully', 200);

use App\Traits\ApiResponses; // Import the ApiResponses trait to use it in the controller example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\ApiSorting;  // Import the ApiSorting trait to use it in the controller example $query = $this->sort(request(), $query, ['id', 'title', 'language', 'category', 'status']);
use App\Traits\ApiFiltering; // Import the ApiFiltering trait to use it in the controller example $query = $this->filter(request(), $query, ['title', 'language', 'category', 'status']);
use App\Traits\SelectableAttributes; // Import the SelectableAttributes trait to use it in the controller example $this->selectAttributes($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiPagination; // Import the ApiPagination trait to use it in the controller example $this->getPerPage($request, $query, 10);
use App\Traits\QueryBuilder; // Import the QueryBuilder trait to use it in the controller example $this->buildQuery($request, $query, $methods);


use Exception; // Import the Exception class
use Illuminate\Validation\ValidationException; // Import the ValidationException class
use Illuminate\Database\Eloquent\ModelNotFoundException; // Import the ModelNotFoundException class


class PostApiController extends Controller {

    // Use the ApiResponses, ApiSorting, ApiFiltering , SelectableAttributes , ApiPagination and QueryBuilder traits
    use ApiResponses, ApiSorting, ApiFiltering , SelectableAttributes , ApiPagination , QueryBuilder;   

    // Validation rules for the post data
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

    // Decode the JSON data from the database to an array
    private function jsonDecode($posts) {
        foreach ($posts as $post) {
            if (isset($post->tags)) {
                $post->tags = json_decode($post->tags);
            }
            if (isset($post->resources)) {
                $post->resources = json_decode($post->resources);
            }
        }        
        return $posts;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request){
        try {
            $queryCheck = Post::firstOrFail(); //! Temporary check to determine if the posts table is empty

            $query = Post::query();
            $methods = [
                'sort' => ['id', 'user_id', 'title', 'language', 'category', 'tags', 'status', 'favorite_count'],
                'filter' => ['title', 'user_id', 'language', 'category', 'tags', 'status'],
                'select' => ['id', 'user_id', 'title', 'code' , 'description', 'resources', 'language', 'category', 'tags', 'status', 'favorite_count'],
                'getPerPage' => 10
            ];

            $query = $this->buildQuery($request, $query, $methods);

            // Check if the query is an instance of JsonResponse and return the response
            if ($query instanceof JsonResponse) {
                return $query;
            }
          
            // Check if the query is empty and return a response message
            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No posts found with the given filters', 200);
            }

            // Decode the JSON data from the database to an array
            $query = $this->jsonDecode($query);

            return $this->successResponse($query, 'Posts retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse('Posts not found', null, 404);
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

            $validatedData['user_id'] = auth()->id();
    
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
            $query = $this->select($request, $query, ['id', 'user_id', 'title', 'code' , 'description', 'resources', 'language', 'category', 'tags', 'status', 'favorite_count']);

            // Check return value of the selectAttributes method and return the response if status code is 400
            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            $post = $query->firstOrFail();

            $post = $this->jsonDecode([$post])[0];

            return $this->successResponse($post, 'Post retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Post not found', ['id' => 'Post with the given ID does not exist'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse {
        try {
            $post = Post::findOrFail($id); 
        
            $validatedData = $request->validate(
                $this->validationRules,         
                $this->getValidationMessages()
            );

            $validatedData['tags'] = json_encode($validatedData['tags']);
            $validatedData['resources'] = json_encode($validatedData['resources']);
        
            $post->update($validatedData);

            return $this->successResponse($post, 'Post update successfully', 200); 
    
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Post not found', ['id' => 'Post with the given ID does not exist'], 404);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse{
        try {
            $post = Post::findOrFail($id);
            $post->delete();
            return $this->successResponse(null, 'Post deleted successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Post not found', ['id' => 'Post with the given ID does not exist'], 404);
        }
    }
}
