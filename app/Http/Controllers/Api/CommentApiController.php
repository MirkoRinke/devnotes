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

use App\Services\ModerationService;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CommentApiController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, ApiSorting, ApiFiltering, ApiSelectable, ApiPagination, QueryBuilder, AuthorizesRequests, RelationLoader;

    /**
     *  The ModerationService instance
     */
    protected $moderationService;

    /**
     *  The constructor for the CommentApiController
     *  It initializes the ModerationService
     *
     * @param ModerationService $moderationService
     */
    public function __construct(ModerationService $moderationService) {
        $this->moderationService = $moderationService;
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
     * Coordinates and manages the comment query configuration
     * 
     * This method serves as a central coordinator that:
     * 1. Delegates relation loading to loadRelations()
     * 2. Applies query building through dynamic method calls (buildQuery/buildQuerySelect)
     * 3. Coordinates field selection processing when requested
     * 
     * By centralizing these operations in one method, it ensures consistent query 
     * configuration across different endpoints (index and show) while delegating
     * the actual implementation to specialized methods.
     *
     * @param Request $request The HTTP request containing query parameters
     * @param \Illuminate\Database\Eloquent\Builder $query The base query to configure
     * @param string $methods The builder method to apply (buildQuery or buildQuerySelect)
     * @return \Illuminate\Database\Eloquent\Builder|JsonResponse The configured query or error response
     */
    function setupCommentQuery(Request $request, $query, $methods) {
        $this->loadRelations($request, $query, [
            ['relation' => 'user', 'foreignKey' => 'user_id', 'columns' => ['id', 'display_name']],
            ['relation' => 'parent', 'foreignKey' => 'parent_id', 'columns' => ['id', 'content', 'reports_count']],
            ['relation' => 'children', 'foreignKey' => 'parent_id', 'columns' => ['id', 'content', 'reports_count']],
            ['relation' => 'children.user', 'foreignKey' => 'user_id', 'columns' => ['id', 'display_name']],
            ['relation' => 'children.parent', 'foreignKey' => 'parent_id', 'columns' => ['id', 'content', 'reports_count']]
        ]);

        /**
         * Apply sorting, filtering, selecting, and pagination
         */
        $query = $this->$methods($request, $query, 'comment');
        if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
            return $query;
        }

        /**
         * Apply field selection
         * This will be used to select the fields in the response
         */
        if ($request->has('select')) {
            $query = $this->applyFieldSelection($request, $query, $methods);
        }

        return $query;
    }


    /**
     * Apply field selection to the query
     * This will be used to select the fields in the response
     *
     * @param Request $request The HTTP request containing query parameters
     * @param \Illuminate\Database\Eloquent\Builder $query The base query to configure
     * @param string $methods The builder method to apply (buildQuery or buildQuerySelect)
     * @return \Illuminate\Database\Eloquent\Builder The configured query
     */
    function applyFieldSelection(Request $request, $query, string $methods) {
        $selectedFields = is_string($request->input('select'))
            ? explode(',', $request->input('select'))
            : $request->input('select');

        /**
         * Merge the Default fields with the selected fields
         */
        $visibleFields = array_merge($selectedFields, ['id', 'user_id', 'parent_id', 'user', 'children', 'parent']);

        /**
         * Set the visible fields on the query object
         * This will be used to select the fields in the response
         */
        if ($methods === 'buildQuery') {
            foreach ($query as $comment) {
                if ($comment->children) {
                    foreach ($comment->children as $child) {
                        $child->setVisible($visibleFields);
                    }
                }
            }
        }

        /**
         * Store visible fields on the query object only in 'buildQuerySelect' mode (used in show method)
         * This allows us to apply the same field selection to related child objects later
         * We don't need this in 'buildQuery' mode (used in index method) as the selection is applied differently to collections
         */
        if ($methods === 'buildQuerySelect') {
            /**
             *  @var \Illuminate\Database\Eloquent\Builder&\stdClass $query 
             *  Dynamically property for Laravel Query Builder
             */
            $query->visibleFields = $visibleFields;
        }

        return $query;
    }


    /**
     * Check if the request has an 'include' parameter
     * If so, make the relations visible
     *
     * @param Request $request The HTTP request containing query parameters
     * @param \Illuminate\Database\Eloquent\Builder $query The base query to configure
     */
    function checkForIncludedRelations(Request $request, $target) {
        if ($request->has('include')) {
            $relations = explode(',', $request->input('include'));

            if (method_exists($target, 'makeVisible')) {
                $target->makeVisible($relations);
            }
        }
        return $target;
    }


    /**
     * Apply report moderation to the comment
     * If the comment has been reported too many times, set the content to "This comment has been reported too many times and is no longer available"
     *
     * @param Comment|Collection $comment
     * @return Comment|Collection
     */
    function replaceReportedContent($comment) {
        if ($comment instanceof Collection) {
            foreach ($comment as $c) {
                $this->applyReportModeration($c);
            }
        } else {
            $this->applyReportModeration($comment);
        }
        return $comment;
    }

    /**
     * Check if the comment has been reported too many times
     * If so, set the content to "This comment has been reported too many times and is no longer available"
     *
     * @param Comment $comment
     * @return Comment
     */
    function applyReportModeration($comment) {
        /**
         * Check if the comment has been reported too many times
         */
        if ($comment->reports_count >= 5) {
            $comment->content = "This comment has been reported too many times and is no longer available";
        }

        /**
         * Check if the comment has a parent and if the parent has been reported too many times
         */
        if ($comment->parent_id !== null) {
            $parentComment = $comment->parent;
            if ($parentComment && $parentComment->reports_count >= 5) {
                $comment->parent_content = "This comment has been reported too many times and is no longer available";
            }
        }

        /**
         * Check if the comment has children and the parent has been reported too many times
         * If so, set the parent_content in the children to "This comment has been reported too many times and is no longer available"
         */
        if ($comment->children && $comment->children->isNotEmpty()) {
            foreach ($comment->children as $child) {
                if ($child->parent_id !== null && $comment->reports_count >= 5) {
                    $child->parent_content = "This comment has been reported too many times and is no longer available";
                }
            }
        }
        return $comment;
    }


    /**
     * Display a listing of the resource.
     */
    public function index(Request $request) {
        try {
            $query = Comment::whereNull('parent_id');

            $query = $this->setupCommentQuery($request, $query, 'buildQuery');
            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            $query = $this->replaceReportedContent($query);
            $query = $this->checkForIncludedRelations($request, $query);


            return $this->successResponse($query, 'Comments retrieved successfully', 200);
        } catch (Exception $e) {
            // return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
            return $this->errorResponse($e->getMessage(), 'SERVER_ERROR', 500);
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

            $comment = Comment::create([
                'user_id' => $request->user()->id,
                'content' => $validatedData['content'],
                'parent_content' => $validatedData['parent_content'] ?? null,
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

            $query = $this->setupCommentQuery($request, $query, 'buildQuerySelect');
            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            $comment = $query->firstOrFail();

            $comment = $this->checkForIncludedRelations($request, $comment);

            if (isset($query->visibleFields) && $comment->children) {
                foreach ($comment->children as $child) {
                    $child->setVisible($query->visibleFields);
                }
            }

            $comment = $this->replaceReportedContent($comment);

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
                $comment->is_deleted = true;
                $comment->content = "This comment has been deleted";
                $comment->save();

                return $this->successResponse($comment, "Comment marked as deleted", 200);
            } else {
                $comment->delete();
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
