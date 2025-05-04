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
     * Constructor to initialize the services
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
        'content' => 'required|string|max:255',
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
         * Use the query builder methods to build the query
         */
        $query = $this->$methods($request, $query, 'comment');
        if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
            return $query;
        }
        return $query;
    }


    /**
     * Get All Comments
     * 
     * Endpoint: GET /comments
     *
     * Retrieves a list of all comments, optionally filtered and sorted.
     * Comments are nested with their replies up to the configured maximum depth.
     * 
     * @group Comments
     *
     * @queryParam select string Select specific fields (id,content,etc). Example: id,content,user_id
     * @queryParam sort string Field to sort by (prefix with - for DESC order). Example: -created_at
     * @queryParam filter[field] string Filter by specific fields. Example: filter[content]=code
     * @queryParam startsWith[field] string Filter by fields that start with a specific value. Example: startsWith[content]=Thanks
     * @queryParam endsWith[field] string Filter by fields that end with a specific value. Example: endsWith[content]=Stores!
     * 
     * @queryParam page integer Page number for pagination. Example: 1
     * @queryParam per_page integer Number of items per page. Example: 15 ( Default: 10 )
     * 
     * @queryParam include string Comma-separated relations to include (user,parent,children). Example: user,children,parent     * 
     * @queryParam user_fields string Fields to include for user relation. Example: id,display_name,role
     * @queryParam parent_fields string Fields to include for parent relation. Example: id,content,updated_at
     * @queryParam children_fields string Fields to include for children relation. Example: id,content,is_deleted,parent_content
     * 
     * Example URL: /?include=children,user,parent&user_fields=display_name&parent_fields=user_id,content
     * 
     * @response status=200 scenario="Comments retrieved" {
     *   "status": "success",
     *   "message": "Comments retrieved successfully",
     *   "code": 200,
     *   "count": 9,
     *   "data": [
     *     {
     *       "id": 1,
     *       "post_id": 1,
     *       "user_id": 4,
     *       "parent_id": null,
     *       "content": "Thanks for this helpful post about Svelte Stores!",
     *       "parent_content": null,
     *       "is_deleted": false,
     *       "depth": 0,
     *       "likes_count": 2,
     *       "reports_count": 0,
     *       "is_updated": false,
     *       "updated_by_role": null,
     *       "moderation_info": null,
     *       "created_at": "2025-04-30T19:34:25.000000Z",
     *       "updated_at": "2025-04-30T19:34:25.000000Z",
     *       "user": {
     *         "id": 4,
     *         "display_name": "Maxi4",
     *       },
     *       "parent": null,
     *       "children": [
     *         {
     *           "id": 2,
     *           "post_id": 1,
     *           "user_id": 7,
     *           "parent_id": 1,
     *           "content": "I'm glad you like it!",
     *           "parent_content": "Thanks for this helpful post about Svelte Stores!",
     *           "is_deleted": false,
     *           "depth": 1,
     *           "likes_count": 1,
     *           "reports_count": 0,
     *           "is_updated": false,
     *           "updated_by_role": null,
     *           "moderation_info": null,
     *           "created_at": "2025-04-30T19:34:25.000000Z",
     *           "updated_at": "2025-04-30T19:34:25.000000Z",
     *           "user": {
     *             "id": 7,
     *             "display_name": "Maxi7",
     *           },
     *           "parent": {
     *             "id": 1,
     *             "user_id": 4,
     *             "content": "Thanks for this helpful post about Svelte Stores!",
     *           },
     *           "children": []
     *         }
     *       ]
     *     }
     *   ]
     * }
     *
     * @response status=200 scenario="No comments found" {
     *   "status": "success",
     *   "message": "No comments found",
     *   "code": 200,
     *   "count": 0,
     *   "data": []
     * }
     *
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     * 
     */
    public function index(Request $request) {
        try {
            $query = Comment::whereNull('parent_id');

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupCommentQuery($request, $query, 'buildQuery');
            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse([], 'No comments found', 200);
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
     * Create a New Comment
     * 
     * Endpoint: POST /comments
     *
     * Creates a new comment for a post. Comments can be standalone or replies to other comments.
     * Nested comments are limited to a maximum depth configured in the system.
     * 
     * @group Comments
     * 
     * @bodyParam content string required The content of the comment. Example: This is a really insightful post!
     * @bodyParam post_id integer required The ID of the post this comment belongs to. Example: 5
     * @bodyParam parent_id integer optional The ID of the parent comment (if this is a reply). Example: 12
     * 
     * @bodyContent {
     *   "content": "This is a really insightful post!",  // required, string, max:255
     *   "post_id": 5,                                    // required, integer, must exist in posts table
     *   "parent_id": 12                                  // optional, integer, must exist in comments table
     * }
     * 
     * @response status=201 scenario="Comment created" {
     *   "status": "success",
     *   "message": "Comment created successfully",
     *   "code": 201,
     *   "count": 1,
     *   "data": {
     *     "id": 15
     *     "post_id": 5,
     *     "user_id": 1,
     *     "parent_id": null,
     *     "content": "This is a really insightful post!",
     *     "parent_content": null,
     *     "depth": 0,
     *     "updated_at": "2025-04-30T21:46:12.000000Z",
     *     "created_at": "2025-04-30T21:46:12.000000Z",
     *   }
     * }
     * 
     * @response status=201 scenario="Reply created" {
     *   "status": "success",
     *   "message": "Comment created successfully",
     *   "code": 201,
     *   "count": 1,
     *   "data": {
     *     "id": 16
     *     "post_id": 5,
     *     "user_id": 1,
     *     "parent_id": 15,
     *     "content": "Adding my thoughts to this thread.",
     *     "parent_content": "This is a really insightful post!",
     *     "depth": 1,
     *     "updated_at": "2025-04-30T21:46:45.000000Z",
     *     "created_at": "2025-04-30T21:46:45.000000Z",
     *   }
     * }
     * 
     * @response status=422 scenario="Validation error - Missing fields" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "content": ["The content field is required."],
     *     "post_id": ["The post id field is required."]
     *   }
     * }
     * 
     * @response status=422 scenario="Validation error - Post not found" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "post_id": ["POST_ID_NOT_FOUND"]
     *   }
     * }
     * 
     * @response status=422 scenario="Post mismatch" {
     *   "status": "error",
     *   "message": "Parent comment must belong to the same post",
     *   "code": 422,
     *   "errors": "COMMENT_POST_MISMATCH"
     * }
     * 
     * @response status=422 scenario="Nesting limit exceeded" {
     *   "status": "error",
     *   "message": "Comments can only be nested to a maximum depth of 2",
     *   "code": 422,
     *   "errors": "COMMENT_NESTING_LIMIT"
     * }
     * 
     * @response status=404 scenario="Parent comment not found" {
     *   "status": "error",
     *   "message": "Parent comment not found",
     *   "code": 404,
     *   "errors": "PARENT_COMMENT_NOT_FOUND"
     * }
     * 
     * @response status=404 scenario="Post not found" {
     *   "status": "error",
     *   "message": "Post not found",
     *   "code": 404,
     *   "errors": "POST_NOT_FOUND"
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
     *      
     */
    public function store(Request $request) {
        try {
            $this->authorize('create', Comment::class);

            $validatedData = $request->validate(
                $this->validationRulesCreate,
                $this->getValidationMessages('Comment')
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
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (ModelNotFoundException $e) {
            if (strpos($e->getMessage(), 'Comment') !== false) {
                return $this->errorResponse('Parent comment not found', 'PARENT_COMMENT_NOT_FOUND', 404);
            } else {
                return $this->errorResponse('Post not found', 'POST_NOT_FOUND', 404);
            }
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Get a Specific Comment
     * 
     * Endpoint: GET /comments/{id}
     *
     * Retrieves a specific comment by its ID, optionally with its relations.
     * Relations like parent comment, user, and nested replies can be included.
     * 
     * @group Comments
     *
     * @urlParam id required The ID of the comment. Example: 1
     * @queryParam select string Select specific fields (id,content,etc). Example: id,content,user_id
     * 
     * @queryParam include string Comma-separated relations to include (user,parent,children). Example: user,children,parent
     * @queryParam user_fields string Fields to include for user relation. Example: id,display_name,role
     * @queryParam parent_fields string Fields to include for parent relation. Example: id,content,updated_at
     * @queryParam children_fields string Fields to include for children relation. Example: id,content,is_deleted,parent_content
     * 
     * Example URL: /comments/1?include=children,user,parent&user_fields=display_name&parent_fields=user_id,content
     * 
     * @response status=200 scenario="Comment retrieved" {
     *   "status": "success",
     *   "message": "Comment retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 1,
     *     "post_id": 1,
     *     "user_id": 4,
     *     "parent_id": null,
     *     "content": "Thanks for this helpful post about Svelte Stores!",
     *     "parent_content": null,
     *     "is_deleted": false,
     *     "depth": 0,
     *     "likes_count": 2,
     *     "reports_count": 0,
     *     "is_updated": false,
     *     "updated_by_role": null,
     *     "moderation_info": null,
     *     "created_at": "2025-04-30T19:34:25.000000Z",
     *     "updated_at": "2025-04-30T19:34:25.000000Z",
     *     "user": {
     *       "id": 4,
     *       "display_name": "Maxi4"
     *     },
     *     "parent": null,
     *     "children": [
     *       {
     *         "id": 2,
     *         "post_id": 1,
     *         "user_id": 7,
     *         "parent_id": 1,
     *         "content": "I'm glad you like it!",
     *         "parent_content": "Thanks for this helpful post about Svelte Stores!",
     *         "is_deleted": false,
     *         "depth": 1,
     *         "likes_count": 1,
     *         "reports_count": 0,
     *         "is_updated": false,
     *         "updated_by_role": null,
     *         "moderation_info": null,
     *         "created_at": "2025-04-30T19:34:25.000000Z",
     *         "updated_at": "2025-04-30T19:34:25.000000Z",
     *         "user": {
     *           "id": 7,
     *           "display_name": "Maxi7"
     *         },
     *         "parent": {
     *           "id": 1,
     *           "user_id": 4,
     *           "content": "Thanks for this helpful post about Svelte Stores!",
     *         },
     *         "children": []
     *       }
     *     ]
     *   }
     * }
     *
     * @response status=404 scenario="Comment not found" {
     *   "status": "error",
     *   "message": "Comment with ID 999 does not exist",
     *   "code": 404,
     *   "errors": "COMMENT_NOT_FOUND"
     * }
     *
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     * 
     */
    public function show(string $id, Request $request) {
        try {
            $query = Comment::where('id', $id);

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupCommentQuery($request, $query, 'buildQuerySelect');
            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            // Need this because the select method returns only the query object
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
     * Update a Comment
     * 
     * Endpoint: PATCH /comments/{id}
     *
     * Updates the content of an existing comment.
     * Regular users can only update their own comments.
     * Admins/moderators can update any comment with a required moderation reason.
     * 
     * @group Comments
     * 
     * @urlParam id required The ID of the comment to update. Example: 15
     * 
     * @bodyParam content string required The updated content of the comment. Example: This comment has been updated with more insights.
     * @bodyParam moderation_reason string required only for admins/moderators. The reason for moderation. Example: Inappropriate language removed.
     * 
     * @bodyContent {
     *   "content": "This comment has been updated with more insights." // required, string, max:255
     * }
     * 
     * @bodyContent scenario="Admin moderation" {
     *   "content": "This comment has been updated with appropriate language.",  // required, string, max:255
     *   "moderation_reason": "Inappropriate language removed"                   // required for admins/moderators, string, max:255
     * }
     * 
     * @response status=200 scenario="Comment updated" {
     *   "status": "success",
     *   "message": "Comment updated successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 15,
     *     "post_id": 5,
     *     "user_id": 1,
     *     "parent_id": null,
     *     "content": "This comment has been updated with more insights.",
     *     "parent_content": null,
     *     "is_deleted": false,
     *     "depth": 0,
     *     "likes_count": 0,
     *     "reports_count": 0,
     *     "is_updated": true,
     *     "updated_by_role": "user",
     *     "moderation_info": null,
     *     "created_at": "2025-04-30T19:45:12.000000Z",
     *     "updated_at": "2025-04-30T20:15:45.000000Z"
     *   }
     * }
     * 
     * @response status=200 scenario="Comment moderated by admin" {
     *   "status": "success",
     *   "message": "Comment updated successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 15,
     *     "post_id": 5,
     *     "user_id": 1,
     *     "parent_id": null,
     *     "content": "This comment has been updated with appropriate language.",
     *     "parent_content": null,
     *     "is_deleted": false,
     *     "depth": 0,
     *     "likes_count": 0,
     *     "reports_count": 0,
     *     "is_updated": true,
     *     "updated_by_role": "admin",
     *     "moderation_info": [
     *       {
     *         "user_id": 1,
     *         "username": "Max Mustermann1",
     *         "role": "admin",
     *         "timestamp": "2025-05-01T16:26:32+02:00",
     *         "reason": "Inappropriate language removed",
     *         "action": "updated",
     *         "changes": {
     *           "content": {
     *             "from": "This comment has been updated with inappropriate language",
     *             "to": "This is an updated comment"
     *           },
     *           "is_updated": {
     *             "from": null,
     *             "to": true
     *           }
     *         }
     *       }
     *     ],
     *     "created_at": "2025-04-30T19:45:12.000000Z",
     *     "updated_at": "2025-04-30T20:15:45.000000Z"
     *   }
     * }
     * 
     * @response status=422 scenario="Comment is deleted" {
     *   "status": "error",
     *   "message": "Comment is deleted",
     *   "code": 422,
     *   "errors": "COMMENT_DELETED"
     * }
     * 
     * @response status=403 scenario="Unauthorized" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     * 
     * @response status=422 scenario="Validation error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "content": ["The content field is required."]
     *   }
     * }
     * 
     * @response status=422 scenario="Missing moderation reason" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "moderation_reason": ["The moderation reason field is required."]
     *   }
     * }
     *
     * @response status=404 scenario="Comment not found" {
     *   "status": "error",
     *   "message": "Comment with ID 999 does not exist",
     *   "code": 404,
     *   "errors": "COMMENT_NOT_FOUND"
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
     * 
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
                $this->getValidationMessages('Comment')
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
                            'updated_by_role' => $request->user()->role // For show the user who updated the comment
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

                // Update the last_comment_at timestamp of the parent post
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
     * Permanently Delete a Comment
     * 
     * Endpoint: DELETE /comments/{id}
     *
     * Permanently removes a comment from the database, along with all associated data.
     * This action also removes all child comments, likes, and reports.
     * This endpoint is restricted to administrators only.
     * 
     * @group Comments
     *
     * @urlParam id required The ID of the comment to delete. Example: 15
     * 
     * @response status=200 scenario="Comment deleted" {
     *   "status": "success",
     *   "message": "Comment deleted successfully",
     *   "code": 200,
     *   "count": 0,
     *   "data": null
     * }
     * 
     * @response status=403 scenario="Unauthorized" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     * 
     * @response status=404 scenario="Comment not found" {
     *   "status": "error",
     *   "message": "Comment with ID 999 does not exist",
     *   "code": 404,
     *   "errors": "COMMENT_NOT_FOUND"
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

                // Update the last_comment_at timestamp of the parent post and comments_count
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
     * Delete a Comment (Soft or Hard Delete)
     * 
     * Endpoint: DELETE /comments/{id}/deleteComment
     *
     * Deletes a comment using a smart approach based on its children:
     * - If the comment has children, it will be soft deleted (marked as deleted but still visible)
     * - If the comment has no children, it will be permanently deleted
     * 
     * Regular users can only delete their own comments.
     * Admins/moderators can delete any comment.
     * 
     * @group Comments
     *
     * @urlParam id required The ID of the comment to delete. Example: 15
     * 
     * @response status=200 scenario="Comment soft-deleted" {
     *   "status": "success",
     *   "message": "Comment marked as deleted",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 15,
     *     "post_id": 5,
     *     "user_id": 1,
     *     "parent_id": null,
     *     "content": "This comment has been deleted",
     *     "parent_content": null,
     *     "is_deleted": true,
     *     "depth": 0,
     *     "likes_count": 0,
     *     "reports_count": 0,
     *     "is_updated": false,
     *     "updated_by_role": null,
     *     "moderation_info": null,
     *     "created_at": "2025-04-30T19:45:12.000000Z",
     *     "updated_at": "2025-05-01T14:26:32.000000Z"
     *   }
     * }
     * 
     * @response status=200 scenario="Comment hard-deleted" {
     *   "status": "success",
     *   "message": "Comment deleted successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": null
     * }
     * 
     * @response status=403 scenario="Unauthorized" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     * 
     * @response status=404 scenario="Comment not found" {
     *   "status": "error",
     *   "message": "Comment with ID 999 does not exist",
     *   "code": 404,
     *   "errors": "COMMENT_NOT_FOUND"
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

                    // Update the last_comment_at timestamp of the parent post
                    $this->commentRelationService->updateLastCommentAt($comment);

                    return $comment;
                });
                return $this->successResponse($comment, "Comment marked as deleted", 200);
            } else {
                $comment = DB::transaction(function () use ($comment) {
                    $comment->delete();

                    // Update the last_comment_at timestamp of the parent post and comments_count
                    $this->commentRelationService->updateLastCommentAt($comment);
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
