<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\Comment;

use App\Traits\ApiResponses; // example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\ApiSorting;  // example $query = $this->sort(request(), $query, ['id', 'title', 'language', 'category', 'status']);
use App\Traits\ApiFiltering; // example $query = $this->filter(request(), $query, ['title', 'language', 'category', 'status']);
use App\Traits\SelectableAttributes; // example $this->selectAttributes($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiPagination; // example $this->getPerPage($request, $query, 10);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Sanctum\PersonalAccessToken;

class CommentApiController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, ApiSorting, ApiFiltering, SelectableAttributes, ApiPagination, QueryBuilder, AuthorizesRequests;

    /**
     * The validation rules for the user profile data
     */
    private $validationRules = [
        'content' => 'required|string|max:255',
        'post_id' => 'required|exists:posts,id',
        'parent_id' => 'nullable|exists:comments,id',
    ];


    /**
     * The methods array contains the methods that are used in the buildQuery method
     */
    private $methods = [
        'sort' => ['id', 'post_id', 'user_id', 'is_deleted', 'is_edited', 'edited_at', 'likes_count', 'reports_count', 'created_at', 'updated_at'],
        'filter' => ['post_id', 'user_id', 'parent_id', 'is_deleted', 'is_edited', 'edited_at', 'likes_count', 'reports_count', 'created_at', 'updated_at'],
        'select' => ['post_id', 'user_id', 'content', 'parent_id', 'is_deleted', 'is_edited', 'edited_at', 'likes_count', 'reports_count', 'created_at', 'updated_at'],
        'getPerPage' => 10
    ];

    /**
     * The maximum depth of comments
     * 
     * Example: If the maxCommentDepth is 2, then a comment can be a reply to another comment, but a reply to a reply is not allowed
     */
    private $maxCommentDepth = 2;

    /**
     * Display a listing of the resource.
     */
    public function index() {
        try {
            $query = Comment::whereNull('parent_id')->with(['user:id,name', 'children.user:id,name']);

            $query = $this->buildQuery(request(), $query, $this->methods);

            return $this->successResponse($query, 'Comments retrieved successfully', 200);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
        try {

            $this->authorize('create', Comment::class);

            $validatedData = $request->validate(
                $this->validationRules,
                $this->getValidationMessages()
            );

            $depth = 0;
            if (!empty($validatedData['parent_id'])) {
                $parentComment = Comment::findOrFail($validatedData['parent_id']);

                if ($parentComment->post_id != $validatedData['post_id']) {
                    return $this->errorResponse("Parent comment must belong to the same post", 'COMMENT_POST_MISMATCH', 422);
                }

                $depth = $parentComment->depth + 1;

                if ($depth >= $this->maxCommentDepth) {
                    return $this->errorResponse("Comments can only be nested to a maximum depth of {$this->maxCommentDepth}", 'COMMENT_NESTING_LIMIT', 422);
                }
            }

            $comment = Comment::create([
                'content' => $validatedData['content'],
                'post_id' => $validatedData['post_id'],
                'user_id' => $request->user()->id,
                'parent_id' => $validatedData['parent_id'] ?? null,
                'depth' => $depth,
            ]);

            return $this->successResponse($comment, 'Comment created successfully', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Post not found', 'POST_NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, Request $request) {
        try {
            $query = Comment::where('id', $id)->with(['user:id,name', 'children.user:id,name']);

            $query = $this->select($request, $query, $this->methods['select']);

            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            $comment = $query->firstOrFail();

            return $this->successResponse($comment, 'Comment retrieved successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("Comment with ID $id does not exist", 'COMMENT_NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id) {
        try {
            $comment = Comment::findOrFail($id);

            $this->authorize('update', $comment);

            $validatedData = $request->validate([
                'content' => 'required|string|max:255',
            ]);

            $comment->content = $validatedData['content'];
            $comment->is_edited = true;
            $comment->edited_at = now();
            $comment->save();

            return $this->successResponse($comment, 'Comment updated successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("Comment with ID $id does not exist", 'COMMENT_NOT_FOUND', 404);
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
            $comment = Comment::findOrFail($id);

            $this->authorize('delete', $comment);

            $comment->delete();
            return $this->successResponse(null, 'Comment deleted successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("Comment with ID $id does not exist", 'COMMENT_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Toggle the delete status of the specified resource.
     */
    public function toggleDeleteStatus(string $id) {
        try {
            $comment = Comment::findOrFail($id);

            $this->authorize('toggleDeleteStatus', $comment);

            $comment->is_deleted = !$comment->is_deleted;
            $comment->save();

            $action = $comment->is_deleted ? 'deleted' : 'restored';

            return $this->successResponse($comment, "Comment $action successfully", 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("Comment with ID $id does not exist", 'COMMENT_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
