<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Post; // Import the Post model to use it in the controller example $posts = Post::all();
use Illuminate\Http\JsonResponse; // Import the JsonResponse class to use it in the controller example return response()->json(['message' => 'Posts retrieved successfully', 'data' => $posts], 200);

use App\Traits\ApiResponses; // Import the ApiResponses trait to use it in the controller example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\ApiSorting;  // Import the ApiSorting trait to use it in the controller example $query = $this->sort(request(), $query, ['id', 'title', 'language', 'category', 'status']);
use App\Traits\ApiFiltering; // Import the ApiFiltering trait to use it in the controller example $query = $this->filter(request(), $query, ['title', 'language', 'category', 'status']);

use Exception; // Import the Exception class
use Illuminate\Validation\ValidationException; // Import the ValidationException class
use Illuminate\Database\Eloquent\ModelNotFoundException; // Import the ModelNotFoundException class


class PostApiController extends Controller {

    use ApiResponses; // Use the ApiResponses trait in the controller
    use ApiSorting; // Use the ApiSorting trait in the controller
    use ApiFiltering; // Use the ApiFiltering trait in the controller

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
            $post->tags = json_decode($post->tags);
            $post->resources = json_decode($post->resources);
        }        
        return $posts;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request){
        try {
            $query = Post::query();

            $query = $this->sort($request, $query, ['id', 'title', 'language', 'category', 'status']); // AllowedColumns is an array of columns that can be sorted

            // Check return value of the sort method and return the response if status code is 400
            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            // Filter the query results based on the request
            $query = $this->filter($request, $query, ['title', 'language', 'category', 'status']); // AllowedColumns is an array of columns that can be filtered

            // Check return value of the filter method and return the response if status code is 400
            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            $query = $query->get();

            // Check if the query is empty and return a response message
            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No posts found with the given filters', 200);
            }

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
    public function show(string $id): JsonResponse {
        try {
        $post = Post::findOrFail($id);
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
