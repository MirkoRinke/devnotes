<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Post; // Import the Post model to use it in the controller example $posts = Post::all();
use Illuminate\Http\JsonResponse; // Import the JsonResponse class to use it in the controller example return response()->json($posts);

use App\Traits\ApiResponses; // Import the ApiResponses trait to use it in the controller example $this->successResponse($post, 'Post created successfully', 201);


class PostApiController extends Controller {

    use ApiResponses; // Use the ApiResponses trait in the controller

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
     * Display a listing of the resource.
     */
    public function index(){
        $posts = Post::all();
        return response()->json($posts);
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse {
        try {
        $post = Post::findOrFail($id); 
        return $this->successResponse($post, 'Post retrieved successfully');
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
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
    
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Post not found', ['id' => 'Post with the given ID does not exist'], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
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
            return $this->successResponse(null, 'Post deleted successfully', 204);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Post not found', ['id' => 'Post with the given ID does not exist'], 404);
        }
    }
}




