<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use App\Models\UserLike;
use App\Models\Post;
use App\Models\Comment;
use App\Models\User;
use App\Traits\ApiResponses;
use App\Traits\QueryBuilder;
use App\Traits\RelationLoader;
use App\Traits\ApiInclude;
use App\Traits\FieldManager;
use App\Traits\AccessFilter;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;

class UserLikeController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, QueryBuilder, RelationLoader, AuthorizesRequests, ApiInclude, FieldManager, AccessFilter;

    /**
     * The validation rule for the like entity
     * 
     * @return array
     * 
     * @example | $this->geValidationRules()
     */
    public function geValidationRules(): array {
        $validationRules = [
            'likeable_type' => 'required|in:post,comment',
            'likeable_id' => 'required|integer',
        ];
        return $validationRules;
    }


    /**
     * Update the likes_count for a likeable entity
     * 
     * @param mixed $likeable The likeable entity (Post or Comment)
     * @param string $method The method to call on the likeable entity (increment or decrement)
     * @return void
     * 
     * @example | $this->updateLikesCount($likeable, 'increment')
     */
    private function updateLikesCount($likeable, $method) {
        $likeable->$method('likes_count');
    }

    /**
     * Setup the query for likes
     * 
     * @param Request $request
     * @param mixed $query
     * @return mixed
     * 
     * @example | $this->setupLikeQuery($request, $query)
     */
    protected function setupLikeQuery(Request $request, $query) {
        $this->modifyRequestSelect($request, ['id', 'user_id', 'likeable_type', 'likeable_id', 'type']);

        $query = $this->loadUserRelation($request, $query);

        $query = $this->buildQuery($request, $query, 'like');

        $query = $this->loadLikeableRelation($request, $query);

        return $query;
    }


    /**
     * Load the user relation
     * 
     * @param Request $request
     * @param mixed $query Builder|LengthAwarePaginator|Collection
     * @return mixed Builder|LengthAwarePaginator|Collection
     * 
     * @example | $this->loadUserRelation($request, $query)
     */
    private function loadUserRelation(Request $request, $query): mixed {
        if ($request->has('include') && in_array('user', explode(',', $request->input('include')))) {
            $query = $this->loadRelations($request, $query, [
                ['relation' => 'user', 'foreignKey' => 'user_id', 'columns' => $this->getRelationFieldsFromRequest($request, 'user', [], ['id', 'display_name', 'role', 'created_at', 'updated_at', 'is_banned', 'was_ever_banned', 'moderation_info'])],
            ]);
        }
        return $query;
    }


    /**
     * Load the likeable polymorphic relation
     * 
     * @param Request $request
     * @param mixed $query Builder|LengthAwarePaginator|Collection
     * @return mixed Builder|LengthAwarePaginator|Collection
     * 
     * @example | $this->loadLikeableRelation($request, $query)
     */
    private function loadLikeableRelation(Request $request, $query): mixed {
        if ($request->has('include') && in_array('likeable', explode(',', $request->input('include')))) {
            $query = $this->loadPolymorphicRelations(
                $request,
                $query,
                'likeable',
                [
                    Post::class => $this->getRelationFieldsFromRequest($request, 'likeable_post', [], ['*']),
                    Comment::class => $this->getRelationFieldsFromRequest($request, 'likeable_comment', [], ['*']),
                ]
            );
        }
        return $query;
    }


    /**
     * Check if the user can like the content
     * 
     * @param mixed $user The authenticated user
     * @param string $likeableType The type of entity to like (Post or Comment)
     * @param mixed $likeable The likeable entity
     * @param int $likeableId The ID of the entity to like
     * @param string $simpleType The simple type of the entity (post or comment)
     * @return JsonResponse|null
     * 
     * @example | $likeableResult = $this->checkIfUserCanLike($user, $likeableType, $likeable, $likeableId, $simpleType);
     *            if ($likeableResult !== null) {
     *              return $likeableResult;
     *            }
     */
    private function checkIfUserCanLike($user, $likeableType, $likeable, $likeableId, $simpleType) {
        if ($likeableType === Post::class && $likeable->user_id == $user->id) {
            return $this->errorResponse('You cannot like your own post', 'CANNOT_LIKE_OWN_POST', 403);
        } else if ($likeableType === Comment::class && $likeable->user_id == $user->id) {
            return $this->errorResponse('You cannot like your own comment', 'CANNOT_LIKE_OWN_COMMENT', 403);
        }

        $existingLike = UserLike::where([
            'user_id' => $user->id,
            'likeable_id' => $likeableId,
            'likeable_type' => $likeableType
        ])->first();

        if ($existingLike) {
            return $this->errorResponse('You have already liked this ' . $simpleType, 'ALREADY_LIKED', 403);
        }
        return null;
    }


    /**
     * Get All Likes
     * 
     * Endpoint: GET /likes
     *
     * Retrieves a list of all likes in the system with support for filtering, sorting,
     * and relation inclusion. Only administrators can access this endpoint.
     *
     * @group Likes
     *
     * @queryParam select string Select specific fields. Example: select=id,user_id,likeable_id
     * @queryParam sort string Sort by field (prefix with - for descending order). Example: sort=-created_at
     * @queryParam filter[type] string Filter by likeable type. Example: filter[type]=post
     * @queryParam filter[user_id] integer Filter by user ID. Example: filter[user_id]=5
     * 
     * @queryParam startsWith[field] string Filter where field starts with given string. Format: field:value. Example: startsWith[created_at]=2025-05-06
     * @queryParam endsWith[field] string Filter where field ends with given string. Format: field:value. Example: endsWith[created_at]=Z
     * 
     * @queryParam include string Comma-separated relations to include. Example: include=user,likeable
     * @queryParam user_fields string When including user relation, specify fields to return. 
     *                              Available fields: id, display_name, role, created_at, updated_at, is_banned, was_ever_banned, moderation_info
     *                              Example: user_fields=id,display_name
     * @queryParam likeable_post_fields string When including likeable relation (for posts), specify fields to return.
     *                              Example: likeable_post_fields=id,title,description
     * @queryParam likeable_comment_fields string When including likeable relation (for comments), specify fields to return.
     *                              Example: likeable_comment_fields=id,content
     * 
     * @queryParam page integer Page number for pagination. Example: page=1
     * @queryParam per_page integer Items per page. Example: per_page=15 (default: 10)
     *
     * Example URL: /likes
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Likes retrieved successfully",
     *   "code": 200,
     *   "count": 2,
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 1,
     *       "likeable_type": "App\\Models\\Post",
     *       "likeable_id": 3,
     *       "type": "post",
     *       "created_at": "2025-05-06T11:24:18.000000Z",
     *       "updated_at": "2025-05-06T11:24:18.000000Z",
     *     },
     *     {
     *       "id": 2,
     *       "user_id": 1,
     *       "likeable_type": "App\\Models\\Comment",
     *       "likeable_id": 8,
     *       "type": "comment",
     *       "created_at": "2025-05-06T12:14:52.000000Z",
     *       "updated_at": "2025-05-06T12:14:52.000000Z",
     *     }
     *   ]
     * }
     * 
     * Example URL: /likes/?include=user&user_fields=id,display_name
     * 
     * @response status=200 scenario="With user relation" {
     *   "status": "success",
     *   "message": "Likes retrieved successfully",
     *   "code": 200,
     *   "count": 2,
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 1,
     *       "likeable_type": "App\\Models\\Post",
     *       "likeable_id": 3,
     *       "type": "post",
     *       "created_at": "2025-05-06T11:24:18.000000Z",
     *       "updated_at": "2025-05-06T11:24:18.000000Z",
     *       "user": {
     *         "id": 1,
     *         "display_name": "admin"
     *       }
     *     }
     *   ]
     * }
     * 
     * Example URL: /likes/?include=likeable&likeable_post_fields=id,title,description
     * 
     * @response status=200 scenario="With likeable" {
     *   "status": "success",
     *   "message": "Likes retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 1,
     *       "likeable_type": "App\\Models\\Post",
     *       "likeable_id": 3,
     *       "type": "post",
     *       "created_at": "2025-05-06T11:24:18.000000Z",
     *       "updated_at": "2025-05-06T11:24:18.000000Z",
     *       "likeable": {
     *         "id": 3,
     *         "title": "Understanding JavaScript Promises",
     *         "description": "A comprehensive guide to JavaScript Promises"
     *       }
     *     }
     *   ]
     * }
     *
     * @response status=200 scenario="No likes found" {
     *   "status": "success",
     *   "message": "No likes found",
     *   "code": 200,
     *   "count": 0,
     *   "data": []
     * }
     *
     * @response status=403 scenario="Unauthorized" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     *
     * @response status=500 scenario="Server Error" {
     *   "status": "error", 
     *   "message": "An unexpected error occurred",
     *   "code": 500,
     *   "errors": "SERVER_ERROR"
     * }
     * 
     * Note: This endpoint requires admin privileges as it accesses all likes in the system.
     * Regular users should use the user-likes endpoints to access their own likes.
     * 
     * @authenticated
     */
    public function index(Request $request) {
        try {
            $this->authorize('viewAny', UserLike::class);

            $query = UserLike::query();

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupLikeQuery($request, $query);
            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse([], 'No likes found', 200);
            }

            $query = $this->manageUsersFieldVisibility($request, $query);

            $query = $this->checkForIncludedRelations($request, $query);

            $query = $this->controlVisibleFields($request, $originalSelectFields, $query);

            return $this->successResponse($query, 'Likes retrieved successfully', 200);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Add a Like
     * 
     * Endpoint: POST /likes
     *
     * Creates a new like for a post or comment. Users cannot like their own content
     * and cannot like the same content multiple times.
     *
     * @group Likes
     *
     * @bodyParam likeable_type string required The type of entity to like ('post' or 'comment'). Example: post
     * @bodyParam likeable_id integer required The ID of the entity to like. Example: 5
     * 
     * @bodyContent {
     *   "likeable_type": "post",
     *   "likeable_id": 5
     * }
     *
     * @response status=201 scenario="Success" {
     *   "status": "success",
     *   "message": "Like added successfully",
     *   "code": 201,
     *   "data": {
     *     "id": 10,
     *     "user_id": 2,
     *     "likeable_id": 5,
     *     "likeable_type": "App\\Models\\Post",
     *     "type": "post",
     *     "updated_at": "2025-05-07T09:42:18.000000Z",
     *     "created_at": "2025-05-07T09:42:18.000000Z"
     *   }
     * }
     *
     * @response status=403 scenario="Cannot like own content" {
     *   "status": "error",
     *   "message": "You cannot like your own post",
     *   "code": 403,
     *   "errors": "CANNOT_LIKE_OWN_POST"
     * }
     *
     * @response status=403 scenario="Already liked" {
     *   "status": "error",
     *   "message": "You have already liked this post",
     *   "code": 403,
     *   "errors": "ALREADY_LIKED"
     * }
     *
     * @response status=404 scenario="Entity not found" {
     *   "status": "error",
     *   "message": "Entity not found",
     *   "code": 404,
     *   "errors": "NOT_FOUND"
     * }
     *
     * @response status=422 scenario="Validation error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "likeable_type": ["LIKEABLE_TYPE_INVALID_OPTION"],
     *     "likeable_id": ["LIKEABLE_ID_FIELD_REQUIRED"]
     *   }
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
            $user = $request->user();

            $validatedData = $request->validate(
                $this->geValidationRules(),
                $this->getValidationMessages('UserLike')
            );

            $typeMap = [
                'post' => Post::class,
                'comment' => Comment::class,
            ];

            $likeableType = $typeMap[$validatedData['likeable_type']];
            $likeableId = $validatedData['likeable_id'];

            $simpleType = $validatedData['likeable_type'];

            $likeable = $likeableType::findOrFail($likeableId);

            $likeableResult = $this->checkIfUserCanLike($user, $likeableType, $likeable, $likeableId, $simpleType);
            if ($likeableResult !== null) {
                return $likeableResult;
            }

            // Add the like and update the likes count for the likeable entity in a transaction
            $like = DB::transaction(function () use ($user, $likeableId, $likeableType, $simpleType, $likeable) {
                $like = UserLike::create([
                    'user_id' => $user->id,
                    'likeable_id' => $likeableId,
                    'likeable_type' => $likeableType,
                    'type' => $simpleType,
                ]);

                $this->updateLikesCount($likeable, 'increment');

                return $like;
            });

            return $this->successResponse($like, 'Like added successfully', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Entity not found', 'NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Remove a Like
     * 
     * Endpoint: DELETE /likes
     *
     * Removes a like from a post or comment. Users can only remove their own likes.
     *
     * @group Likes
     *
     * @bodyParam likeable_type string required The type of entity to unlike ('post' or 'comment'). Example: post
     * @bodyParam likeable_id integer required The ID of the entity to unlike. Example: 5
     * 
     * @bodyContent {
     *   "likeable_type": "post",
     *   "likeable_id": 5
     * }
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Like removed successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": null
     * }
     *
     * @response status=404 scenario="Like not found" {
     *   "status": "error",
     *   "message": "Entity not found",
     *   "code": 404,
     *   "errors": "NOT_FOUND"
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
     *     "likeable_type": ["LIKEABLE_TYPE_INVALID_OPTION"],
     *     "likeable_id": ["LIKEABLE_ID_FIELD_REQUIRED"]
     *   }
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
    public function destroy(Request $request) {
        try {
            $user = $request->user();

            $validatedData = $request->validate(
                $this->geValidationRules(),
                $this->getValidationMessages('UserLike')
            );

            $typeMap = [
                'post' => Post::class,
                'comment' => Comment::class,
            ];

            $likeableType = $typeMap[$validatedData['likeable_type']];
            $likeableId = $validatedData['likeable_id'];

            $like = UserLike::where([
                'user_id' => $user->id,
                'likeable_id' => $likeableId,
                'likeable_type' => $likeableType
            ])->firstOrFail();

            $this->authorize('delete', $like);

            $likeable = $like->likeable;

            // Remove the like and update the likes count for the likeable entity in a transaction
            DB::transaction(function () use ($like, $likeable) {
                $this->updateLikesCount($likeable, 'decrement');
                $like->delete();
            });

            return $this->successResponse(null, 'Like removed successfully', 200);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Entity not found', 'NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Get Liked Posts
     * 
     * Endpoint: GET /user-likes/{userId}/posts
     *
     * Retrieves all posts that have been liked by the specified user, with support for
     * filtering, sorting, and relation inclusion.
     *
     * @group User Likes
     *
     * @queryParam select string Select specific fields from posts. Example: select=id,title,code
     * @queryParam sort string Sort by field (prefix with - for descending order). Example: sort=-created_at
     * @queryParam filter[field] string Filter by specific fields. Example: filter[language]=php
     * 
     * @queryParam startsWith[field] string Filter where field starts with given string. Example: startsWith[title]=How
     * @queryParam endsWith[field] string Filter where field ends with given string. Example: endsWith[title]=Guide
     * 
     * @queryParam include string Comma-separated relations to include. Example: include=user,comments
     * @queryParam user_fields string When including user relation, specify fields to return. 
     *                              Available fields: id, display_name, role, created_at, updated_at, is_banned, was_ever_banned, moderation_info
     *                              Example: user_fields=id,display_name
     * 
     * @queryParam page integer Page number for pagination. Example: page=1
     * @queryParam per_page integer Items per page. Example: per_page=15
     *
     * Example URL: /user-likes/5/posts
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Liked posts retrieved successfully",
     *   "code": 200,
     *   "count": 2,
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 1,
     *       "title": "Svelte Store: Simple State Management",
     *       "code": "import { writable } from 'svelte/store';",
     *       "description": "Discover the benefits of Svelte Stores for simple state management.",
     *       "images": [],
     *       "videos": [],
     *       "resources": [],
     *       "external_source_previews": [
     *         {
     *           "url": "https://picsum.photos/200",
     *           "type": "images",
     *           "domain": "picsum.photos"
     *         },
     *         {
     *           "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
     *           "type": "videos",
     *           "domain": "www.youtube.com"
     *         },
     *         {
     *           "url": "https://svelte.dev/docs#run-time-store",
     *           "type": "resources",
     *           "domain": "svelte.dev"
     *         }
     *       ],
     *       "language": [
     *         "HTML",
     *         "JavaScript"
     *       ],
     *       "category": "Frontend",
     *       "post_type": "tutorial",
     *       "technology": [
     *         "Svelte"
     *       ],
     *       "tags": [
     *         "svelte",
     *         "store",
     *         "state-management"
     *       ],
     *       "status": "published",
     *       "favorite_count": 2,
     *       "likes_count": 2,
     *       "reports_count": 0,            || Admin and Moderator only
     *       "comments_count": 3,
     *       "is_updated": false,
     *       "updated_by_role": null,
     *       "last_comment_at": "2025-05-07T22:10:44.000000Z",
     *       "history": null,
     *       "moderation_info": null,       || Admin and Moderator only
     *       "created_at": "2025-05-05T16:12:42.000000Z",
     *       "updated_at": "2025-05-08T16:27:48.000000Z"
     *     },
     *     {
     *       "id": 2,
     *       "user_id": 4,
     *       "title": "Laravel 8: Eloquent ORM",
     *       "code": "use App\\Models\\User;",
     *       "description": "Learn how to use Eloquent ORM in Laravel 8.",
     *       "images": [],
     *       "videos": [],
     *       "resources": [],
     *       "external_source_previews": [
     *         {
     *           "url": "https://picsum.photos/200",
     *           "type": "images",
     *           "domain": "picsum.photos"
     *         },
     *         {
     *           "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
     *           "type": "videos",
     *           "domain": "www.youtube.com"
     *         },
     *         {
     *           "url": "https://laravel.com/docs/8.x/eloquent",
     *           "type": "resources",
     *           "domain": "laravel.com"
     *         }
     *       ],
     *       "language": [
     *         "PHP"
     *       ],
     *       "category": "Backend",
     *       "post_type": "tutorial",
     *       "technology": [
     *         "Laravel"
     *       ],
     *       "tags": [
     *         "laravel",
     *         "eloquent",
     *         "orm"
     *       ],
     *       "status": "published",
     *       "favorite_count": 3,
     *       "likes_count": 2,
     *       "reports_count": 0,            || Admin and Moderator only
     *       "comments_count": 3,
     *       "is_updated": false,
     *       "updated_by_role": null,
     *       "last_comment_at": "2025-05-05T16:12:42.000000Z",
     *       "history": null,
     *       "moderation_info": null,       || Admin and Moderator only
     *       "created_at": "2025-05-05T16:12:42.000000Z",
     *       "updated_at": "2025-05-08T15:54:25.000000Z"
     *     }
     *   ]
     * }
     *
     * Example URL: /user-likes/5/posts/?include=user&user_fields=id,display_name
     * 
     * @response status=200 scenario="With included user relation" {
     *   "status": "success",
     *   "message": "Liked posts retrieved successfully",
     *   "code": 200,
     *   "count": 2,
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 1,
     *       "title": "Svelte Store: Simple State Management",
     *       "code": "import { writable } from 'svelte/store';",
     *       "description": "Discover the benefits of Svelte Stores for simple state management.",
     *       "images": [],
     *       "videos": [],
     *       "resources": [],
     *       "external_source_previews": [
     *         {
     *           "url": "https://picsum.photos/200",
     *           "type": "images",
     *           "domain": "picsum.photos"
     *         },
     *         {
     *           "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
     *           "type": "videos",
     *           "domain": "www.youtube.com"
     *         },
     *         {
     *           "url": "https://svelte.dev/docs#run-time-store",
     *           "type": "resources",
     *           "domain": "svelte.dev"
     *         }
     *       ],
     *       "language": [
     *         "HTML",
     *         "JavaScript"
     *       ],
     *       "category": "Frontend",
     *       "post_type": "tutorial",
     *       "technology": [
     *         "Svelte"
     *       ],
     *       "tags": [
     *         "svelte",
     *         "store",
     *         "state-management"
     *       ],
     *       "status": "published",
     *       "favorite_count": 2,
     *       "likes_count": 2,
     *       "reports_count": 0,            || Admin and Moderator only
     *       "comments_count": 3,
     *       "is_updated": false,
     *       "updated_by_role": null,
     *       "last_comment_at": "2025-05-07T22:10:44.000000Z",
     *       "history": null,
     *       "moderation_info": null,       || Admin and Moderator only
     *       "created_at": "2025-05-05T16:12:42.000000Z",
     *       "updated_at": "2025-05-08T16:27:48.000000Z",
     *       "user": {
     *         "id": 1,
     *         "display_name": "Admin"
     *       }
     *     },
     *     {
     *       "id": 2,
     *       "user_id": 4,
     *       "title": "Laravel 8: Eloquent ORM",
     *       "code": "use App\\Models\\User;",
     *       "description": "Learn how to use Eloquent ORM in Laravel 8.",
     *       "images": [],
     *       "videos": [],
     *       "resources": [],
     *       "external_source_previews": [
     *         {
     *           "url": "https://picsum.photos/200",
     *           "type": "images",
     *           "domain": "picsum.photos"
     *         },
     *         {
     *           "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
     *           "type": "videos",
     *           "domain": "www.youtube.com"
     *         },
     *         {
     *           "url": "https://laravel.com/docs/8.x/eloquent",
     *           "type": "resources",
     *           "domain": "laravel.com"
     *         }
     *       ],
     *       "language": [
     *         "PHP"
     *       ],
     *       "category": "Backend",
     *       "post_type": "tutorial",
     *       "technology": [
     *         "Laravel"
     *       ],
     *       "tags": [
     *         "laravel",
     *         "eloquent",
     *         "orm"
     *       ],
     *       "status": "published",
     *       "favorite_count": 3,
     *       "likes_count": 2,
     *       "reports_count": 0,            || Admin and Moderator only
     *       "comments_count": 3,
     *       "is_updated": false,
     *       "updated_by_role": null,
     *       "last_comment_at": "2025-05-05T16:12:42.000000Z",
     *       "history": null,
     *       "moderation_info": null,       || Admin and Moderator only
     *       "created_at": "2025-05-05T16:12:42.000000Z",
     *       "updated_at": "2025-05-08T15:54:25.000000Z",
     *       "user": {
     *         "id": 4,
     *         "display_name": "Maxi4"
     *       }
     *     }
     *   ]
     * }
     * 
     * @response status=200 scenario="No liked posts found" {
     *   "status": "success",
     *   "message": "No liked posts found",
     *   "code": 200,
     *   "count": 0,
     *   "data": []
     * }
     * 
     * @response status=403 scenario="User not found" {
     *   "status": "error",
     *   "message": "User not found",
     *   "code": 404,
     *   "errors": "USER_NOT_FOUND"
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
    public function getLikedPosts(Request $request, string $userId) {
        try {
            $user = User::findOrFail($userId);

            $likedPostIds = $user->likes()
                ->where('likeable_type', Post::class)
                ->pluck('likeable_id');

            $query = Post::whereIn('id', $likedPostIds);

            $query = $this->loadUserRelation($request, $query);

            $query = $this->applyPostAccessFilters($request, $query);

            $query = $this->buildQuery($request, $query, 'post');

            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse([], 'No liked posts found', 200);
            }

            $query = $this->managePostsFieldVisibility($request, $query);

            $query = $this->checkForIncludedRelations($request, $query);

            return $this->successResponse($query, 'Liked posts retrieved successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('User not found', 'USER_NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Get Liked Comments
     * 
     * Endpoint: GET /user-likes/{userId}/comments
     *
     * Retrieves all comments that have been liked by the specified user, with support for
     * filtering, sorting, and relation inclusion.
     *
     * @group User Likes
     *
     * @queryParam select string Select specific fields from comments. Example: select=id,content,post_id
     * @queryParam sort string Sort by field (prefix with - for descending order). Example: sort=-created_at
     * @queryParam filter[field] string Filter by specific fields. Example: filter[post_id]=5
     * 
     * @queryParam startsWith[field] string Filter where field starts with given string. Example: startsWith[content]=Thank
     * @queryParam endsWith[field] string Filter where field ends with given string. Example: endsWith[content]=question
     * 
     * @queryParam include string Comma-separated relations to include. Example: include=user,post
     * @queryParam user_fields string When including user relation, specify fields to return. 
     *                              Available fields: id, display_name, role, created_at, updated_at
     *                              Example: user_fields=id,display_name
     * 
     * @queryParam page integer Page number for pagination. Example: page=1
     * @queryParam per_page integer Items per page. Example: per_page=15
     *
     * Example URL: /user-likes/4/comments
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Liked comments retrieved successfully",
     *   "code": 200,
     *   "count": 2,
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
     *       "likes_count": 1,
     *       "reports_count": 0,           || Admin and Moderator only
     *       "is_updated": false,
     *       "updated_by_role": null,
     *       "moderation_info": null,      || Admin and Moderator only
     *       "created_at": "2025-05-05T16:12:42.000000Z",
     *       "updated_at": "2025-05-08T18:16:03.000000Z"
     *     },
     *     {
     *       "id": 4,
     *       "post_id": 2,
     *       "user_id": 4,
     *       "parent_id": 3,
     *       "content": "Absolutely, I use it in all my Laravel projects.",
     *       "parent_content": "Eloquent is truly one of the best ORMs for PHP!",
     *       "is_deleted": false,
     *       "depth": 1,
     *       "likes_count": 1,
     *       "reports_count": 0,           || Admin and Moderator only
     *       "is_updated": false,
     *       "updated_by_role": null,
     *       "moderation_info": null,      || Admin and Moderator only
     *       "created_at": "2025-05-05T16:12:42.000000Z",
     *       "updated_at": "2025-05-08T18:15:56.000000Z"
     *     }
     *   ]
     * }
     *
     * Example URL: /user-likes/4/comments/?include=user&user_fields=id,display_name
     * 
     * @response status=200 scenario="With included user relation" {
     *   "status": "success",
     *   "message": "Liked comments retrieved successfully",
     *   "code": 200,
     *   "count": 2,
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
     *       "likes_count": 1,
     *       "reports_count": 0,           || Admin and Moderator only
     *       "is_updated": false,
     *       "updated_by_role": null,
     *       "moderation_info": null,      || Admin and Moderator only
     *       "created_at": "2025-05-05T16:12:42.000000Z",
     *       "updated_at": "2025-05-08T18:16:03.000000Z",
     *       "user": {
     *         "id": 4,
     *         "display_name": "Maxi4"
     *       }
     *     },
     *     {
     *       "id": 4,
     *       "post_id": 2,
     *       "user_id": 4,
     *       "parent_id": 3,
     *       "content": "Absolutely, I use it in all my Laravel projects.",
     *       "parent_content": "Eloquent is truly one of the best ORMs for PHP!",
     *       "is_deleted": false,
     *       "depth": 1,
     *       "likes_count": 1,
     *       "reports_count": 0,           || Admin and Moderator only
     *       "is_updated": false,
     *       "updated_by_role": null,
     *       "moderation_info": null,      || Admin and Moderator only
     *       "created_at": "2025-05-05T16:12:42.000000Z",
     *       "updated_at": "2025-05-08T18:15:56.000000Z",
     *       "user": {
     *         "id": 4,
     *         "display_name": "Maxi4"
     *       }
     *     }
     *   ]
     * }
     *
     * @response status=200 scenario="No liked comments found" {
     *   "status": "success",
     *   "message": "No liked comments found",
     *   "code": 200,
     *   "count": 0,
     *   "data": []
     * }
     *
     * @response status=403 scenario="User not found" {
     *   "status": "error",
     *   "message": "User not found",
     *   "code": 404,
     *   "errors": "USER_NOT_FOUND"
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
    public function getLikedComments(Request $request, string $userId) {
        try {
            $user = User::findOrFail($userId);

            $likedCommentIds = $user->likes()
                ->where('likeable_type', Comment::class)
                ->pluck('likeable_id');

            $query = Comment::whereIn('id', $likedCommentIds);

            $query = $this->loadUserRelation($request, $query);

            $query = $this->buildQuery($request, $query, 'comment');

            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse([], 'No liked comments found', 200);
            }

            $query = $this->manageCommentsFieldVisibility($request, $query);

            $query = $this->checkForIncludedRelations($request, $query);

            return $this->successResponse($query, 'Liked comments retrieved successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('User not found', 'USER_NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
