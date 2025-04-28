<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use App\Models\Comment;

use App\Traits\ApiResponses; // example $this->successResponse($comment, 'Comment retrieved successfully', 200);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);
use App\Traits\ApiInclude; // example $this->checkForIncludedRelations($request, $query);
use App\Traits\RelationLoader;  // examples:
// - Single relation: $this->loadRelation($request, $query, 'user', 'user_id', ['id', 'display_name'])
// - Multiple relations: $this->loadRelations($request, $query, [
//     ['relation' => 'user', 'foreignKey' => 'user_id', 'columns' => ['id', 'display_name']],
//     ['relation' => 'post', 'foreignKey' => 'post_id', 'columns' => ['id', 'title']]
// ])

use App\Services\ModerationService;
use App\Services\CommentModerationService;
use App\Services\CommentRelationService;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Illuminate\Auth\Access\AuthorizationException;

class CommentApiController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, QueryBuilder, ApiInclude, RelationLoader, AuthorizesRequests;

    /**
     *  The Service used in the controller
     */
    protected $moderationService;
    protected $commentModerationService;
    protected $commentRelationService;

    /**
     *  The constructor for the CommentApiController
     *  It initializes the ModerationService
     *  It also initializes the CommentModerationService
     *
     * @param ModerationService $moderationService
     * @param CommentModerationService $commentModerationService
     */
    public function __construct(
        ModerationService $moderationService,
        CommentModerationService $commentModerationService,
        CommentRelationService $commentRelationService
    ) {
        $this->moderationService = $moderationService;
        $this->commentModerationService = $commentModerationService;
        $this->commentRelationService = $commentRelationService;
    }

    /**
     * The validation rules for the Create method
     */
    private $validationRulesCreate = [
        'content' => 'required|string|max:255',
        'post_id' => 'required|exists:posts,id',
        'parent_id' => 'nullable|exists:comments,id',
    ];


    /**
     * The validation rules for the Update method
     */
    private $validationRulesUpdate = [
        'content' => 'sometimes|required|string|max:255',
    ];

    /**
     * The maximum depth of comments
     * 
     * Example: If the maxCommentDepth is 2, then a comment can be a reply to another comment, but a reply to a reply is not allowed
     */
    private $maxCommentDepth = 2;


    /**
     * Setup the comment query
     * This method is used to set up the query for the comments
     * It applies sorting, filtering, selecting, and pagination
     * It also loads the relations for the comments
     * 
     * @param Request $request
     * @param $query
     * @param $methods
     * @return mixed
     */
    protected function setupCommentQuery(Request $request, $query, $methods): mixed {

        $relationKeyFields = $this->getRelationKeyFields($request, ['children' => 'id', 'parent' => 'parent_id',  'user' => 'user_id']);

        $this->modifyRequestSelect($request, [...['id', 'reports_count'], ...$relationKeyFields]);

        $select = $this->getSelectFields($request);

        $this->loadRelations($request, $query, [
            ['relation' => 'user', 'foreignKey' => 'user_id', 'columns' => $this->getRelationFieldsFromRequest($request, 'user')],
            ['relation' => 'parent', 'foreignKey' => 'parent_id', 'columns' => $this->getRelationFieldsFromRequest($request, 'parent', ['id', 'reports_count'])],

            ['relation' => 'children', 'foreignKey' => 'parent_id', 'columns' => $select],
            ['relation' => 'children.user', 'foreignKey' => 'user_id', 'columns' => $this->getRelationFieldsFromRequest($request, 'user')],
            ['relation' => 'children.parent', 'foreignKey' => 'parent_id', 'columns' => $this->getRelationFieldsFromRequest($request, 'parent', ['id', 'reports_count'])],

            ['relation' => 'children.children', 'foreignKey' => 'parent_id', 'columns' => $select],
            ['relation' => 'children.children.user', 'foreignKey' => 'user_id', 'columns' => $this->getRelationFieldsFromRequest($request, 'user')],
            ['relation' => 'children.children.parent', 'foreignKey' => 'parent_id', 'columns' => $this->getRelationFieldsFromRequest($request, 'parent', ['id', 'reports_count'])],
        ]);


        /**
         * Use the query builder to apply sorting, filtering, selecting, and pagination
         */
        $query = $this->$methods($request, $query, 'comment');
        if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
            return $query;
        }
        return $query;
    }


    /**
     * Display a listing of the resource.
     */
    public function index(Request $request) {
        try {
            $query = Comment::whereNull('parent_id');

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupCommentQuery($request, $query, 'buildQuery');
            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            $query = $this->checkForIncludedRelations($request, $query);
            $query = $this->commentModerationService->replaceReportedContent($query);

            $query = $this->controlVisibleFields($request, $originalSelectFields, $query);

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
                $this->validationRulesCreate,
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

                $validatedData['parent_content'] = $parentComment->content;
            }

            $comment = DB::transaction(function () use ($request, $validatedData, $depth) {
                $comment = Comment::create([
                    'user_id' => $request->user()->id,
                    'content' => $validatedData['content'],
                    'parent_content' => $validatedData['parent_content'] ?? null,
                    'post_id' => $validatedData['post_id'],
                    'parent_id' => $validatedData['parent_id'] ?? null,
                    'depth' => $depth,
                ]);

                // Update last_comment_at
                $this->commentRelationService->updateLastCommentAt($comment);

                // Update comments_count
                $this->commentRelationService->updateCommentsCount($comment, 'increment');

                return $comment;
            });

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

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupCommentQuery($request, $query, 'buildQuerySelect');
            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            $comment = $query->firstOrFail();

            $comment = $this->checkForIncludedRelations($request, $comment);
            $comment = $this->commentModerationService->replaceReportedContent($comment);

            $comment = $this->controlVisibleFields($request, $originalSelectFields, $comment);

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

            if ($comment->is_deleted && $request->user()->role === 'user') {
                return $this->errorResponse('Comment is deleted', 'COMMENT_DELETED', 422);
            }

            /** 
             * Check if the user is an admin or moderator and if they are not the owner of the comment
             * If so, add the moderation_reason to the validation rules
             */
            if ($request->user()->id !== $comment->user_id && ($request->user()->role === 'admin' || $request->user()->role === 'moderator')) {
                $this->validationRulesUpdate['moderation_reason'] = 'required|string|max:255';
            }

            $validatedData = $request->validate(
                $this->validationRulesUpdate,
                $this->getValidationMessages()
            );


            /** 
             * Check if the user is an admin or moderator and if they are not the owner of the comment
             * If so, handle the moderation update
             */
            if ($request->user()->id !== $comment->user_id && ($request->user()->role === 'admin' || $request->user()->role === 'moderator')) {
                $comment = $this->moderationService->handleModerationUpdate(
                    $comment,
                    array_merge(
                        $validatedData,
                        [
                            'is_updated' => true,
                            'updated_by_role' => $request->user()->role // For show the user who updated the post for the user
                        ]
                    ),
                    $request,
                    ['content'],
                    'comment'
                );
                $comment->save();

                return $this->successResponse($comment, 'Comment updated successfully', 200);
            }

            $validatedData = array_merge(
                $validatedData,
                ['is_updated' => true],
                ['updated_by_role' => $request->user()->role]
            );

            $comment = DB::transaction(function () use ($comment, $validatedData) {
                // Update the comment
                $comment->update($validatedData);

                // Update last_comment_at
                $this->commentRelationService->updateLastCommentAt($comment);
                return $comment;
            });


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

            $comment = DB::transaction(function () use ($comment) {
                // Delete all reports and likes associated with the comment
                $this->commentRelationService->deleteReports($comment);
                $this->commentRelationService->deleteLikes($comment);

                // Delete all child comments
                $this->commentRelationService->deleteChildren($comment);

                // Delete the comment
                $comment->delete();

                // Update last_comment_at and comments_count
                $this->commentRelationService->updateLastCommentAt($comment);
                $this->commentRelationService->updateCommentsCount($comment, 'decrement');

                return $comment;
            });

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
     * Handle the deletion of a comment.
     * If the comment has children, it will be soft deleted.
     * If the comment has no children, it will be permanently deleted.
     */
    public function deleteComment(string $id) {
        try {
            $comment = Comment::findOrFail($id);

            $this->authorize('deleteComment', $comment);

            $hasChildren = $comment->children()->exists();

            if ($hasChildren) {
                $comment = DB::transaction(function () use ($comment) {
                    $comment->is_deleted = true;
                    $comment->content = "This comment has been deleted";
                    $comment->save();
                    // Update last_comment_at
                    $this->commentRelationService->updateLastCommentAt($comment);

                    return $comment;
                });
                return $this->successResponse($comment, "Comment marked as deleted", 200);
            } else {
                $comment = DB::transaction(function () use ($comment) {
                    $comment->delete();

                    // Update last_comment_at
                    $this->commentRelationService->updateLastCommentAt($comment);

                    // Update comments_count
                    $this->commentRelationService->updateCommentsCount($comment, 'decrement');

                    return $comment;
                });
                return $this->successResponse(null, "Comment deleted successfully", 200);
            }
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("Comment with ID $id does not exist", 'COMMENT_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
