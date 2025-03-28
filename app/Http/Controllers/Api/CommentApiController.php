<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\Comment;

use App\Traits\ApiResponses; // example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\ApiSorting;  // example $query = $this->sort(request(), $query, ['id', 'title', 'language', 'category', 'status']);
use App\Traits\ApiFiltering; // example $query = $this->filter(request(), $query, ['title', 'language', 'category', 'status']);
use App\Traits\ApiSelectable; // example $this->select($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiPagination; // example $this->getPerPage($request, $query, 10);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);
use App\Traits\RelationLoader;  // examples:
// - Single relation: $this->loadRelation($request, $query, 'user', 'user_id', ['id', 'display_name'])
// - Multiple relations: $this->loadRelations($request, $query, [
//     ['relation' => 'user', 'foreignKey' => 'user_id', 'columns' => ['id', 'display_name']],
//     ['relation' => 'post', 'foreignKey' => 'post_id', 'columns' => ['id', 'title']]
// ])

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
    use ApiResponses, ApiSorting, ApiFiltering, ApiSelectable, ApiPagination, QueryBuilder, AuthorizesRequests, RelationLoader;

    /**
     * The validation rules for the user profile data
     */
    private $validationRules = [
        'content' => 'required|string|max:255',
        'post_id' => 'required|exists:posts,id',
        'parent_id' => 'nullable|exists:comments,id',
    ];

    /**
     * The maximum depth of comments
     * 
     * Example: If the maxCommentDepth is 2, then a comment can be a reply to another comment, but a reply to a reply is not allowed
     */
    private $maxCommentDepth = 2;


    /**
     * Apply the query logic for the comment resource
     *
     * @param Request $request
     * @param $query
     * @param string $methods
     * @return mixed
     */
    public function applyCommentQueryLogic(Request $request, $query, $methods) {
        $selectParam = $request->input('select');
        $hasUserId = false;

        if (is_string($selectParam)) {
            $hasUserId = str_contains($selectParam, 'user_id');
        } elseif (is_array($selectParam)) {
            $hasUserId = in_array('user_id', $selectParam);
        }

        if (!$request->has('select') || $hasUserId) {
            $this->loadRelations($request, $query, [
                ['relation' => 'user', 'foreignKey' => 'user_id', 'columns' => ['id', 'display_name']],
                ['relation' => 'children.user', 'foreignKey' => 'user_id', 'columns' => ['id', 'display_name']]
            ]);

            $query = $this->$methods($request, $query, 'comment');

            if ($request->has('select')) {
                $selectedFields = is_string($request->input('select'))
                    ? explode(',', $request->input('select'))
                    : $request->input('select');

                $visibleFields = array_merge($selectedFields, ['id', 'user_id', 'parent_id', 'user', 'children']);

                foreach ($query as $comment) {
                    if ($methods === 'buildQuery') {
                        if ($comment->children) {
                            foreach ($comment->children as $child) {
                                $child->setVisible($visibleFields);
                            }
                        }
                    } else if ($methods === 'buildQuerySelect') {
                        $query->visibleFields = $visibleFields;
                    }
                }
            }
        } else {
            $this->loadRelation($request, $query, 'user', 'user_id', ['id', 'display_name']);
            $query = $this->$methods($request, $query, 'comment');
        }
        return $query;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request) {
        try {
            $query = Comment::whereNull('parent_id');

            $query = $this->applyCommentQueryLogic($request, $query, 'buildQuery');

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
                'user_id' => $request->user()->id,
                'content' => $validatedData['content'],
                'post_id' => $validatedData['post_id'],
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
            $query = Comment::where('id', $id);

            $query = $this->applyCommentQueryLogic($request, $query, 'buildQuerySelect');

            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            $comment = $query->firstOrFail();

            if (isset($query->visibleFields) && $comment->children) {
                foreach ($comment->children as $child) {
                    $child->setVisible($query->visibleFields);
                }
            }

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

            $updateRules = ['content' => $this->validationRules['content']];

            $validatedData = $request->validate(
                $updateRules,
                $this->getValidationMessages()
            );

            $validatedData = array_merge(
                $validatedData,
                ['updated_by' => $request->user()->id],
                ['is_edited' => true],
                ['updated_by_role' => $request->user()->role]
            );

            $comment->update($validatedData);

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
