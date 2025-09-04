<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use App\Models\Comment;

use App\Traits\ApiResponses;
use App\Traits\QueryBuilder;
use App\Traits\ApiInclude;
use App\Traits\RelationLoader;
use App\Traits\FieldManager;
use App\Traits\UserLikeHelper;
use App\Traits\UserFollowerHelper;
use App\Traits\CommentQuerySetup;

use App\Services\ModerationService;
use App\Services\CommentRelationService;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * Controller handling all comment-related API endpoints.
 * 
 * This controller provides CRUD operations for comments, with special handling for:
 * - Nested comment hierarchies (limited to a configurable depth)
 * - Field selection and visibility based on user role
 * - Moderation actions for admins/moderators
 * - Smart deletion strategies (soft vs. hard delete)
 */
class CommentController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, QueryBuilder, ApiInclude, RelationLoader, AuthorizesRequests, FieldManager, UserLikeHelper, UserFollowerHelper, CommentQuerySetup;

    /**
     *  The Service used in the controller
     */
    protected $moderationService;
    protected $commentRelationService;

    /**
     * Constructor to initialize the services
     */
    public function __construct(
        ModerationService $moderationService,
        CommentRelationService $commentRelationService
    ) {
        $this->moderationService = $moderationService;
        $this->commentRelationService = $commentRelationService;
    }

    /**
     * The validation rules for the Create method
     * 
     * @return array
     * 
     * @example | $this->getValidationRulesCreate()
     */
    public function getValidationRulesCreate(): array {
        $validationRulesCreate = [
            'content' => 'required|string|max:255',
            'post_id' => 'required|exists:posts,id',
            'parent_id' => 'nullable|exists:comments,id',
        ];
        return $validationRulesCreate;
    }


    /**
     * The validation rules for the Update method
     * 
     * @return array
     * 
     * @example | $this->getValidationRulesUpdate()
     */
    public function getValidationRulesUpdate(): array {
        $validationRulesUpdate = [
            'content' => 'required|string|max:255',
        ];
        return $validationRulesUpdate;
    }

    /**
     * The maximum depth of comments
     * 
     * Example: If the maxCommentDepth is 2, then a comment can be a reply to another comment, but a reply to a reply is not allowed
     */
    private $maxCommentDepth = 2;


    /**
     * List All Comments
     * 
     * Endpoint: GET /comments
     *
     * Retrieves a list of comments with support for filtering, sorting, field selection, relation inclusion, and pagination.  
     * **By default, results are paginated.**
     *
     * Comments can be nested (children) up to a maximum depth.  
     * Relations (`user`, `parent`, `children`) can be included via the `include` parameter.
     *
     * You can use the `*_fields` parameter for all relations (e.g. `user_fields`, `parent_fields`, `children_fields`)
     * to specify which fields should be returned for each relation.
     * 
     * Example: `/comments?include=user,children&user_fields=id,display_name&children_fields=id,content`
     *
     * @group Comments
     *
     * @queryParam select   See [ApiSelectable](#apiselectable) for field selection details. 
     * @see \App\Traits\ApiSelectable::select()
     * 
     * @queryParam sort     See [ApiSorting](#apisorting) for sorting details.
     * @see \App\Traits\ApiSorting::sort()
     * 
     * @queryParam filter   See [ApiFiltering](#apifiltering) for filtering details. 
     * @see \App\Traits\ApiFiltering::filter()
     * 
     * @queryParam include  See [ApiInclude](#apiinclude) for relation inclusion details (e.g. user, parent, children). 
     * @see \App\Traits\ApiInclude::getRelationKeyFields()
     * 
     * @queryParam *_fields string See [ApiInclude](#apiinclude). When including a relation, specify fields to return. Example: user_fields=id,display_name
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest() for dynamic includes
     *
     * @queryParam page     Pagination, see [ApiPagination](#apipagination).
     * @see \App\Traits\ApiPagination::paginate()
     * 
     * @queryParam per_page Pagination, see [ApiPagination](#apipagination). 
     * @see \App\Traits\ApiPagination::paginate()
     * 
     * @queryParam setLimit Disables pagination and limits the number of results. See [ApiLimit](#apilimit).
     * @see \App\Traits\ApiLimit::setLimit()
     *
     * Example URL: /comments/
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Comments retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "post_id": 1,
     *       "user_id": 4,
     *       "parent_id": null,
     *       "content": "Great explanation! This really helped me understand Svelte Stores.",
     *       "parent_content": null,
     *       "is_deleted": false,
     *       "depth": 0,
     *       "likes_count": 2,
     *       "reports_count": 0,                || Admin and Moderator only
     *       "is_updated": false,
     *       "updated_by_role": null,
     *       "moderation_info": [],             || Admin and Moderator only
     *       "created_at": "2025-04-30T19:34:25.000000Z",
     *       "updated_at": "2025-04-30T19:34:25.000000Z",
     *       "is_liked": false                  || Virtual field, true if the authenticated user has liked this comment
     *     }
     *   ]
     * }
     *
     * Example URL: /comments/?include=user,parent,children
     *
     * @response status=200 scenario="Success with includes" {
     *   "status": "success",
     *   "message": "Comments retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *      ..... || Same comment data as above
     *       "user": {
     *         "id": 222,
     *         "display_name": "carmine.little",
     *         "role": "user",
     *       "avatar_items": {
     *           "duck": "/ducks/yellow_duck.webp",
     *           "background": "/background/beach.webp",
     *           "ear_accessory": "/ear_accessory/stud_earring.webp",
     *           "eye_accessory": "/eye_accessory/sunglasses.webp",
     *           "head_accessory": "/head_accessory/top_hat.webp",
     *           "neck_accessory": "/neck_accessory/gold_chain.webp",
     *           "chest_accessory": "/chest_accessory/bow_tie.webp"
     *         },
     *         "created_at": "2025-06-29T00:07:03.000000Z",
     *         "updated_at": "2025-06-29T00:07:03.000000Z",
     *         "is_banned": null,               || Admin and Moderator only
     *         "was_ever_banned": false,        || Admin and Moderator only
     *         "moderation_info": [],           || Admin and Moderator only
     *         "is_following": false            || Virtual field, true if the authenticated user is following this user
     *       },
     *       "parent": {
     *         "id": 801,
     *         "post_id": 68,
     *         "user_id": 383,
     *         "parent_id": null,
     *         "content": "Great explanation! This really helped me understand Svelte Stores.",
     *         "parent_content": null,
     *         "is_deleted": false,
     *         "depth": 0,
     *         "likes_count": 4,
     *         "reports_count": 0,                || Admin and Moderator only
     *         "is_updated": false,
     *         "updated_by_role": null,
     *         "moderation_info": [],             || Admin and Moderator only
     *         "created_at": "2025-06-29T00:07:37.000000Z",
     *         "updated_at": "2025-06-29T00:08:11.000000Z"
     *       },
     *       "children": [
     *         {
     *           "id": 821,
     *           "post_id": 68,
     *           "user_id": 204,
     *           "parent_id": 802,
     *           "content": "Thanks for the clarification! Now it makes sense.",
     *           "parent_content": "I agree, the documentation could use more real-world examples.",
     *           "is_deleted": false,
     *           "depth": 2,
     *           "likes_count": 5,
     *           "reports_count": 0,                || Admin and Moderator only
     *           "is_updated": false,
     *           "updated_by_role": null,
     *           "moderation_info": [],             || Admin and Moderator only
     *           "created_at": "2025-06-29T00:07:37.000000Z",
     *           "updated_at": "2025-06-29T00:08:11.000000Z",
     *           "user": {
     *             "id": 204,
     *             "display_name": "coralie89",
     *             "role": "user",
     *           "avatar_items": {
     *             "duck": "/ducks/yellow_duck.webp",
     *             "background": "/background/beach.webp",
     *             "ear_accessory": "/ear_accessory/stud_earring.webp",
     *             "eye_accessory": "/eye_accessory/sunglasses.webp",
     *             "head_accessory": "/head_accessory/top_hat.webp",
     *             "neck_accessory": "/neck_accessory/gold_chain.webp",
     *             "chest_accessory": "/chest_accessory/bow_tie.webp"
     *           },
     *             "created_at": "2025-06-29T00:07:03.000000Z",
     *             "updated_at": "2025-06-29T00:07:03.000000Z"
     *             "is_banned": null,               || Admin and Moderator only
     *             "was_ever_banned": false,        || Admin and Moderator only
     *             "moderation_info": [],           || Admin and Moderator only
     *             "is_following": false            || Virtual field, true if the authenticated user is following this user
     *           },
     *           "parent": {
     *             "id": 802,
     *             "post_id": 68,
     *             "user_id": 222,
     *             "parent_id": 801,
     *             "content": "I agree, the documentation could use more real-world examples.",
     *             "parent_content": "Great explanation! This really helped me understand Svelte Stores.",
     *             "is_deleted": false,
     *             "depth": 1,
     *             "likes_count": 3,
     *             "reports_count": 0,                || Admin and Moderator only
     *             "is_updated": false,
     *             "updated_by_role": null,
     *             "moderation_info": [],             || Admin and Moderator only
     *             "created_at": "2025-06-29T00:07:37.000000Z",
     *             "updated_at": "2025-06-29T00:08:11.000000Z"
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
     * Note:  
     * - Comments are nested up to the configured maximum depth.
     */
    public function index(Request $request) {
        try {
            $query = Comment::query();

            $user = $this->getAuthenticatedUser($request);

            $originalSelectFields = $this->getSelectFields($request);

            $comments = $this->setupCommentQuery($request, $query, 'buildQuery');
            if ($comments instanceof JsonResponse) {
                return $comments;
            }

            if ($comments->isEmpty()) {
                return $this->successResponse([], 'No comments found', 200);
            }

            $comments = $this->manageCommentsFieldVisibility($request, $comments);

            $comments = $this->checkForIncludedRelations($request, $comments);

            $comments = $this->controlVisibleFields($request, $originalSelectFields, $comments);

            $comments = $this->isLiked($request, $user, $comments, 'comment', $originalSelectFields);

            $comments = $this->isFollowing($request, $comments);

            return $this->successResponse($comments, 'Comments retrieved successfully', 200);
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
     * @bodyParam content string required The content of the comment. Example: This is a comment for Post (Top-Level Comment)
     * @bodyParam post_id integer required The ID of the post this comment belongs to. Example: 8
     * @bodyParam parent_id integer optional The ID of the parent comment (if this is a reply). Example: 1151
     * 
     * @bodyContent {
     *   "content": "This is a comment for Post (Top-Level Comment)",   || required, string, max:255
     *   "post_id": 8,                                                  || required, integer, must exist in posts table
     *   "parent_id": 1151                                              || optional, integer, must exist in comments table
     * }
     * 
     * @response status=201 scenario="Comment created" {
     *   "status": "success",
     *   "message": "Comment created successfully",
     *   "code": 201,
     *   "count": 1,
     *   "data": {
     *     "id": 1151,
     *     "post_id": 8,
     *     "user_id": 1,
     *     "content": "This is a comment for Post (Top-Level Comment)",
     *     "parent_content": null,
     *     "depth": 0,
     *     "updated_at": "2025-06-29T22:20:35.000000Z",
     *     "created_at": "2025-06-29T22:20:35.000000Z"
     *   }
     * }
     * 
     * @response status=201 scenario="Reply created" {
     *   "status": "success",
     *   "message": "Comment created successfully",
     *   "code": 201,
     *   "count": 1,
     *   "data": {
     *     "id": 1152,
     *     "post_id": 8,
     *     "user_id": 1,
     *     "parent_id": 1151,
     *     "content": "This is a comment for comment",
     *     "parent_content": "This is a comment for Post (Top-Level Comment)",
     *     "depth": 1,
     *     "updated_at": "2025-06-29T22:22:44.000000Z",
     *     "created_at": "2025-06-29T22:22:44.000000Z"
     *   }
     * }
     * 
     * @response status=422 scenario="Validation error - Missing fields" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "content": ["CONTENT_FIELD_REQUIRED"],
     *     "post_id": ["POST_ID_FIELD_REQUIRED"]
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
     *   "message": "Parent comment with ID 999 does not exist",
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
     */
    public function store(Request $request) {
        try {
            $this->authorize('create', Comment::class);

            $user = $request->user();

            $validatedData = $request->validate(
                $this->getValidationRulesCreate(),
                $this->getValidationMessages('Comment')
            );

            $parentComment = null;

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

            $comment = DB::transaction(function () use ($user, $validatedData, $depth, $parentComment) {

                $comment = new Comment($validatedData);
                $comment->user_id = $user->id;
                $comment->parent_content = $parentComment->content ?? null;
                $comment->depth = $depth;
                $comment->moderation_info = [];
                $comment->save();

                $this->commentRelationService->updateLastCommentAt($comment);
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
                return $this->errorResponse("Parent comment with ID {$validatedData['parent_id']} does not exist", 'PARENT_COMMENT_NOT_FOUND', 404);
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
     * Retrieves a specific comment by its ID, with support for field selection and relation inclusion.
     * Relations (`user`, `parent`, `children`) can be included via the `include` parameter.
     *
     * You can use the `*_fields` parameter for all relations (e.g. `user_fields`, `parent_fields`, `children_fields`)
     * to specify which fields should be returned for each relation.
     * 
     * Example: `/comments/802?include=user,parent,children&user_fields=id,display_name&children_fields=id,content`
     *
     * @group Comments
     *
     * @queryParam select   See [ApiSelectable](#apiselectable) for field selection details. 
     * @see \App\Traits\ApiSelectable::select()
     * 
     * @queryParam include  See [ApiInclude](#apiinclude) for relation inclusion details (e.g. user, parent, children). 
     * @see \App\Traits\ApiInclude::getRelationKeyFields()
     * 
     * @queryParam *_fields string See [ApiInclude](#apiinclude). When including a relation, specify fields to return. Example: user_fields=id,display_name
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest() for dynamic includes
     *
     * Example URL: /comments/802
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Comment retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 802,
     *     "post_id": 68,
     *     "user_id": 222,
     *     "parent_id": 801,
     *     "content": "Great explanation! This really helped me understand Svelte Stores.",
     *     "parent_content": "Thanks for this helpful post about Svelte Stores!",
     *     "is_deleted": false,
     *     "depth": 1,
     *     "likes_count": 3,
     *     "reports_count": 0,                || Admin and Moderator only
     *     "is_updated": false,
     *     "updated_by_role": null,
     *     "moderation_info": [],             || Admin and Moderator only
     *     "created_at": "2025-06-29T00:07:37.000000Z",
     *     "updated_at": "2025-06-29T19:30:30.000000Z",
     *     "is_liked": false                  || Virtual field, true if the authenticated user has liked this comment
     *   }
     * }
     *
     * Example URL: /comments/802?include=user,parent,children
     *
     * @response status=200 scenario="Success with includes" {
     *   "status": "success",
     *   "message": "Comment retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *    ..... || Same comment data as above
     *     "user": {
     *       "id": 222,
     *       "display_name": "carmine.little",
     *       "role": "user",
     *       "avatar_items": {
     *         "duck": "/ducks/yellow_duck.webp",
     *         "background": "/background/beach.webp",
     *         "ear_accessory": "/ear_accessory/stud_earring.webp",
     *         "eye_accessory": "/eye_accessory/sunglasses.webp",
     *         "head_accessory": "/head_accessory/top_hat.webp",
     *         "neck_accessory": "/neck_accessory/gold_chain.webp",
     *         "chest_accessory": "/chest_accessory/bow_tie.webp"
     *       },
     *       "created_at": "2025-06-29T00:07:03.000000Z",
     *       "updated_at": "2025-06-29T00:07:03.000000Z",
     *       "is_banned": null,               || Admin and Moderator only
     *       "was_ever_banned": false,        || Admin and Moderator only
     *       "moderation_info": [],           || Admin and Moderator only
     *       "is_following": false            || Virtual field, true if the authenticated user is following this user
     *     },
     *     "parent": {
     *       "id": 801,
     *       "post_id": 68,
     *       "user_id": 383,
     *       "parent_id": null,
     *       "content": "Thanks for this helpful post about Svelte Stores!",
     *       "parent_content": null,
     *       "is_deleted": false,
     *       "depth": 0,
     *       "likes_count": 4,
     *       "reports_count": 0,                || Admin and Moderator only
     *       "is_updated": false,
     *       "updated_by_role": null,
     *       "moderation_info": [],             || Admin and Moderator only
     *       "created_at": "2025-06-29T00:07:37.000000Z",
     *       "updated_at": "2025-06-29T00:08:11.000000Z"
     *     },
     *     "children": [
     *       {
     *         "id": 821,
     *         "post_id": 68,
     *         "user_id": 204,
     *         "parent_id": 802,
     *         "content": "Thanks for the clarification! Now it makes sense.",
     *         "parent_content": "Great explanation! This really helped me understand Svelte Stores.",
     *         "is_deleted": false,
     *         "depth": 2,
     *         "likes_count": 5,
     *         "reports_count": 0,                || Admin and Moderator only
     *         "is_updated": false,
     *         "updated_by_role": null,
     *         "moderation_info": [],             || Admin and Moderator only
     *         "created_at": "2025-06-29T00:07:37.000000Z",
     *         "updated_at": "2025-06-29T00:08:11.000000Z",
     *         "user": {
     *           "id": 204,
     *           "display_name": "coralie89",
     *           "role": "user",
     *           "avatar_items": {
     *             "duck": "/ducks/yellow_duck.webp",
     *             "background": "/background/beach.webp",
     *             "ear_accessory": "/ear_accessory/stud_earring.webp",
     *             "eye_accessory": "/eye_accessory/sunglasses.webp",
     *             "head_accessory": "/head_accessory/top_hat.webp",
     *             "neck_accessory": "/neck_accessory/gold_chain.webp",
     *             "chest_accessory": "/chest_accessory/bow_tie.webp"
     *           },
     *           "created_at": "2025-06-29T00:07:03.000000Z",
     *           "updated_at": "2025-06-29T00:07:03.000000Z"
     *           "is_banned": null,               || Admin and Moderator only
     *           "was_ever_banned": false,        || Admin and Moderator only
     *           "moderation_info": [],           || Admin and Moderator only
     *           "is_following": false            || Virtual field, true if the authenticated user is following this user
     *         },
     *         "parent": {
     *           "id": 802,
     *           "post_id": 68,
     *           "user_id": 222,
     *           "parent_id": 801,
     *           "content": "Great explanation! This really helped me understand Svelte Stores.",
     *           "parent_content": "Thanks for this helpful post about Svelte Stores.",
     *           "is_deleted": false,
     *           "depth": 1,
     *           "likes_count": 3,
     *           "reports_count": 0,                || Admin and Moderator only
     *           "is_updated": false,
     *           "updated_by_role": null,
     *           "moderation_info": [],             || Admin and Moderator only
     *           "created_at": "2025-06-29T00:07:37.000000Z",
     *           "updated_at": "2025-06-29T19:30:30.000000Z"
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
     * Note:  
     * - Comments are nested up to the configured maximum depth.
     */
    public function show(string $id, Request $request) {
        try {
            $query = Comment::where('id', $id);

            $user = $this->getAuthenticatedUser($request);

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupCommentQuery($request, $query, 'buildQuerySelect');
            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            /**
             * Need this because the buildQuerySelect method returns only the query object
             */
            $comment = $query->firstOrFail();

            $comment = $this->manageCommentsFieldVisibility($request, $comment);

            $comment = $this->checkForIncludedRelations($request, $comment);

            $comment = $this->controlVisibleFields($request, $originalSelectFields, $comment);

            $comment = $this->isLiked($request, $user, $comment, 'comment', $originalSelectFields);

            $comment = $this->isFollowing($request, $comment);

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
     * Owners can update their own comments. Admins and moderators can update any comment (with moderation reason).
     * 
     * @group Comments
     * 
     * @urlParam id required The ID of the comment to update. Example: 1151
     * 
     * @bodyParam content string required The updated content of the comment. Example: This is an updated for (Top-Level Comment) comment
     * @bodyParam moderation_reason string required only for admins/moderators. The reason for moderation. Example: Content changed
     * 
     * @bodyContent {
     *   "content": "This is an updated for (Top-Level Comment) comment"    || required, string, max:255
     * }
     * 
     * @bodyContent scenario="Admin moderation" {
     *   "content": "This is an updated for (Top-Level Comment) comment",   || required, string, max:255
     *   "moderation_reason": "Content changed"                             || required for admins/moderators, string, max:255
     * }
     * 
     * @response status=200 scenario="Comment updated (own comment)" {
     *   "status": "success",
     *   "message": "Comment updated successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 1151,
     *     "post_id": 8,
     *     "user_id": 1,
     *     "parent_id": null,
     *     "content": "This is an updated for (Top-Level Comment) comment",
     *     "parent_content": null,
     *     "is_deleted": false,
     *     "depth": 0,
     *     "likes_count": 0,
     *     "reports_count": 0,                  || Admin and Moderator only
     *     "is_updated": true,
     *     "updated_by_role": "admin",
     *     "moderation_info": [],               || Admin and Moderator only
     *     "created_at": "2025-06-29T22:20:35.000000Z",
     *     "updated_at": "2025-06-29T22:41:24.000000Z"
     *   }
     * }
     * 
     * @response status=200 scenario="Comment updated (admin/moderator)" {
     *   "status": "success",
     *   "message": "Comment updated successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 1150,
     *     "post_id": 307,
     *     "user_id": 3,
     *     "parent_id": null,
     *     "content": "This is an updated for (Top-Level Comment) comment",
     *     "parent_content": null,
     *     "is_deleted": true,
     *     "depth": 0,
     *     "likes_count": 2,
     *     "reports_count": 0,                  || Admin and Moderator only
     *     "is_updated": true,
     *     "updated_by_role": "admin",
     *     "moderation_info": [
     *       {
     *         "user_id": 1,
     *         "username": "Admin",
     *         "role": "admin",
     *         "timestamp": "2025-06-30T00:59:18+02:00",
     *         "reason": "Content changed",
     *         "action": "updated",
     *         "changes": null
     *       },
     *       {
     *         "role": "admin",
     *         "action": "updated",
     *         "reason": "Content changed",
     *         "changes": {
     *           "content": {
     *             "to": "This is an updated for (Top-Level Comment) comment",
     *             "from": "Content deleted (Factory)"
     *           }
     *         },
     *         "user_id": 1,
     *         "username": "Admin",
     *         "timestamp": "2025-06-30T00:45:32+02:00"
     *       }
     *     ],
     *     "created_at": "2025-06-29T00:07:44.000000Z",
     *     "updated_at": "2025-06-29T22:59:18.000000Z"
     *   }
     * }
     * 
     * @response status=200 scenario="Comment moderated by admin (classic example)" {
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
     *     "reports_count": 0,                  || Admin and Moderator only
     *     "is_updated": true,
     *     "updated_by_role": "admin",
     *     "moderation_info": [                 || Admin and Moderator only
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
     */
    public function update(Request $request, string $id) {
        try {
            $comment = Comment::findOrFail($id);

            $this->authorize('update', $comment);

            $user = $request->user();

            if ($comment->is_deleted && $user->role === 'user') {
                return $this->errorResponse('Comment is deleted', 'COMMENT_DELETED', 422);
            }

            $validationRulesUpdate = $this->getValidationRulesUpdate();

            $isContentModeration = $user->id !== $comment->user_id && ($user->role === 'admin' || $user->role === 'moderator');

            if ($isContentModeration) {
                $validationRulesUpdate['moderation_reason'] = 'required|string|max:255';
            }

            $validatedData = $request->validate(
                $validationRulesUpdate,
                $this->getValidationMessages('Comment')
            );

            if ($isContentModeration) {
                /**
                 *  Update the comment and set the moderation_info field and apply all changes from validatedData to the model
                 */
                $comment = $this->moderationService->handleModerationUpdate(
                    $comment,
                    $validatedData,
                    $request,
                    ['content'],
                    'comment'
                );

                $comment->is_updated = true;
                $comment->updated_by_role = $user->role;
                $comment->save();

                $comment = $this->manageCommentsFieldVisibility($request, $comment);

                return $this->successResponse($comment, 'Comment updated successfully', 200);
            }

            $comment = DB::transaction(function () use ($comment, $validatedData, $user) {

                $comment->fill($validatedData);

                $comment->is_updated = true;
                $comment->updated_by_role = $user->role;
                $comment->save();

                $this->commentRelationService->updateLastCommentAt($comment);

                return $comment;
            });

            $comment = $this->manageCommentsFieldVisibility($request, $comment);

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
     * 
     * Only administrators can access this endpoint.
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
     * Note:
     * - This endpoint deletes the comment and all its child comments (recursively), including all related likes and reports.
     * - Use this for complete thread removal (e.g. spam or abusive content).
     * - For soft-delete (preserving children), see `deleteComment`.
     * 
     * @authenticated
     */
    public function destroy(string $id) {
        try {
            $comment = Comment::findOrFail($id);

            $this->authorize('delete', $comment);

            DB::transaction(function () use ($comment) {

                $this->commentRelationService->deleteReports($comment);
                $this->commentRelationService->deleteLikes($comment);

                $this->commentRelationService->deleteChildren($comment);

                $this->commentRelationService->updateLastCommentAt($comment);
                $this->commentRelationService->updateCommentsCount($comment, 'decrement');

                $comment->delete();
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
     * - If the comment has no children, it will be permanently deleted (including all related likes and reports)
     * 
     * Regular users can only delete their own comments.
     * Admins and moderators can delete any comment.
     * 
     * @group Comments
     *
     * @urlParam id required The ID of the comment to delete. Example: 501
     * 
     * @response status=200 scenario="Comment soft-deleted" {
     *   "status": "success",
     *   "message": "Comment marked as deleted",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 501,
     *     "post_id": 167,
     *     "user_id": 179,
     *     "parent_id": null,
     *     "content": "This comment has been deleted",
     *     "parent_content": null,
     *     "is_deleted": true,
     *     "depth": 0,
     *     "likes_count": 3,
     *     "reports_count": 0,                                  || Admin and Moderator only   
     *     "is_updated": false,
     *     "updated_by_role": null,
     *     "moderation_info": [],                               || Admin and Moderator only 
     *     "created_at": "2025-06-29T00:07:32.000000Z",
     *     "updated_at": "2025-06-29T23:39:05.000000Z"
     *   }
     * }
     * 
     * @response status=200 scenario="Comment hard-deleted" {
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
     * Note:
     * - Soft delete: Only the `is_deleted` flag is set and the content is replaced if children exist.
     * - Hard delete: The comment is removed including all related likes and reports if no children exist.
     * - Regular users can only delete their own comments, admins/moderators can delete any comment.
     * 
     * @authenticated
     */
    public function deleteComment(Request $request, string $id) {
        try {
            $comment = Comment::findOrFail($id);

            $this->authorize('deleteComment', $comment);

            $hasChildren = $comment->children()->exists();

            if ($hasChildren) {
                $comment = DB::transaction(function () use ($comment) {
                    $comment->is_deleted = true;
                    $comment->content = "This comment has been deleted";
                    $comment->save();

                    $this->commentRelationService->updateLastCommentAt($comment);

                    return $comment;
                });

                $comment = $this->manageCommentsFieldVisibility($request, $comment);

                return $this->successResponse($comment, "Comment marked as deleted", 200);
            } else {
                DB::transaction(function () use ($comment) {
                    $this->commentRelationService->deleteReports($comment);
                    $this->commentRelationService->deleteLikes($comment);

                    $this->commentRelationService->updateLastCommentAt($comment);
                    $this->commentRelationService->updateCommentsCount($comment, 'decrement');

                    $comment->delete();
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
