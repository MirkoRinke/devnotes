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
use App\Traits\FavoriteHelper;
use App\Traits\LikeHelper;
use App\Traits\FollowerHelper;
use App\Traits\PostQuerySetup;
use App\Traits\CommentQuerySetup;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;

class UserLikeController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, QueryBuilder, RelationLoader, AuthorizesRequests, ApiInclude, FieldManager, AccessFilter, FavoriteHelper, LikeHelper, FollowerHelper, PostQuerySetup, CommentQuerySetup;

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

        $query = $this->loadUserRelation($request, $query, 'user_id');

        $query = $this->buildQuery($request, $query, 'like');

        $query = $this->loadLikeableRelation($request, $query);

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
                    Post::class => $this->getRelationFieldsFromRequest($request, 'likeable_post', ['user_id'], ['*']),
                    Comment::class => $this->getRelationFieldsFromRequest($request, 'likeable_comment', ['reports_count'], ['*']),
                ]
            );
        }
        return $query;
    }


    /**
     * Get All Likes
     * 
     * Endpoint: GET /likes
     *
     * Retrieves a list of all likes in the system with support for filtering, sorting,
     * and relation inclusion. Only administrators and moderators can access this endpoint.
     *
     * @group Likes
     *
     * @queryParam select   See [ApiSelectable](#apiselectable) for field selection details.
     * @see \App\Traits\ApiSelectable::select()
     * 
     * @queryParam sort     See [ApiSorting](#apisorting) for sorting details.
     * @see \App\Traits\ApiSorting::sort()
     * 
     * @queryParam filter[type] string Filter by likeable type. Example: filter[type]=post
     * @see \App\Traits\ApiFiltering::filter()
     * 
     * @queryParam filter[user_id] integer Filter by user ID. Example: filter[user_id]=5
     * @see \App\Traits\ApiFiltering::filter()
     * 
     * @queryParam include  See [ApiInclude](#apiinclude) for relation inclusion details (e.g. user, likeable).
     * @see \App\Traits\ApiInclude::getRelationKeyFields()
     * 
     * @queryParam user_fields string See [ApiInclude](#apiinclude). When including user relation, specify fields to return. Example: user_fields=id,display_name
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest()
     * 
     * @queryParam likeable_post_fields string See [ApiInclude](#apiinclude). When including likeable relation (for posts), specify fields to return. Example: likeable_post_fields=id,title,description
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest()
     * 
     * @queryParam likeable_comment_fields string See [ApiInclude](#apiinclude). When including likeable relation (for comments), specify fields to return. Example: likeable_comment_fields=id,content
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest()
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
     * Example URL: /likes
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Likes retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 356,
     *       "likeable_type": "App\\Models\\Post",
     *       "likeable_id": 129,
     *       "type": "post",
     *       "created_at": "2025-07-09T17:27:27.000000Z",
     *       "updated_at": "2025-07-09T17:27:27.000000Z"
     *     }
     *   ]
     * }
     *
     * Example URL: /likes/?include=user,likeable
     *
     * @response status=200 scenario="Success (with includes, post, user)" {
     *   "status": "success",
     *   "message": "Likes retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 356,
     *       "likeable_type": "App\\Models\\Post",
     *       "likeable_id": 129,
     *       "type": "post",
     *       "created_at": "2025-07-09T17:27:27.000000Z",
     *       "updated_at": "2025-07-09T17:27:27.000000Z",
     *       "likeable": {
     *         "id": 129,
     *         "user_id": 1,
     *         "title": "Angular Understanding JavaScript Promises",
     *         "code": "const promise = new Promise((resolve, reject) => {});",
     *         "description": "A comprehensive guide to JavaScript Promises",
     *         "images": [
     *           "https://picsum.photos/id/324/200/300",
     *           "https://picsum.photos/id/898/200/300",
     *           "https://picsum.photos/id/422/200/300"
     *         ],
     *         "videos": [
     *           "https://www.youtube.com/watch?v=dQw4w9WgXcQ"
     *         ],
     *         "resources": [
     *           "https://www.php.net/manual/de/index.php",
     *           "https://developer.mozilla.org/de/docs/Web/JavaScript/Guide/Introduction",
     *           "https://developer.mozilla.org/de/docs/Web/CSS"
     *         ],
     *         "external_source_previews": [
     *           {
     *             "url": "https://picsum.photos/id/324/200/300",
     *             "type": "images",
     *             "domain": "picsum.photos"
     *           },
     *           {
     *             "url": "https://picsum.photos/id/898/200/300",
     *             "type": "images",
     *             "domain": "picsum.photos"
     *           },
     *           {
     *             "url": "https://picsum.photos/id/422/200/300",
     *             "type": "images",
     *             "domain": "picsum.photos"
     *           },
     *           {
     *             "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
     *             "type": "videos",
     *             "domain": "www.youtube.com"
     *           },
     *           {
     *             "url": "https://www.php.net/manual/de/index.php",
     *             "type": "resources",
     *             "domain": "www.php.net"
     *           },
     *           {
     *             "url": "https://developer.mozilla.org/de/docs/Web/JavaScript/Guide/Introduction",
     *             "type": "resources",
     *             "domain": "developer.mozilla.org"
     *           },
     *           {
     *             "url": "https://developer.mozilla.org/de/docs/Web/CSS",
     *             "type": "resources",
     *             "domain": "developer.mozilla.org"
     *           }
     *         ],
     *         "category": "Cloud Computing",
     *         "post_type": "Tutorial",
     *         "status": "Draft",
     *         "favorite_count": 4,
     *         "likes_count": 3,
     *         "reports_count": 0,
     *         "comments_count": 2,
     *         "is_updated": false,
     *         "updated_by_role": null,
     *         "last_comment_at": "2025-07-09T17:27:16.000000Z",
     *         "history": [],
     *         "moderation_info": [],
     *         "created_at": "2025-07-09T17:26:53.000000Z",
     *         "updated_at": "2025-07-09T17:28:09.000000Z"
     *       },
     *       "user": {
     *         "id": 356,
     *         "display_name": "bessie.hermiston",
     *         "role": "user",
     *         "created_at": "2025-07-09T17:26:48.000000Z",
     *         "updated_at": "2025-07-09T17:26:48.000000Z",
     *         "is_banned": null,
     *         "was_ever_banned": false,
     *         "moderation_info": []
     *       }
     *     }
     *   ]
     * }
     * 
     * Example URL: /likes/?include=user,likeable
     *
     * @response status=200 scenario="Success (with includes, comment, user)" {
     *   "status": "success",
     *   "message": "Likes retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 1255,
     *       "user_id": 335,
     *       "likeable_type": "App\\Models\\Comment",
     *       "likeable_id": 27,
     *       "type": "comment",
     *       "created_at": "2025-07-09T17:27:34.000000Z",
     *       "updated_at": "2025-07-09T17:27:34.000000Z",
     *       "likeable": {
     *         "id": 27,
     *         "post_id": 344,
     *         "user_id": 1,
     *         "parent_id": null,
     *         "content": "This is a comment on the post",
     *         "parent_content": null,
     *         "is_deleted": false,
     *         "depth": 0,
     *         "likes_count": 5,
     *         "reports_count": 0,
     *         "is_updated": false,
     *         "updated_by_role": null,
     *         "moderation_info": [],
     *         "created_at": "2025-07-09T17:27:05.000000Z",
     *         "updated_at": "2025-07-09T17:27:52.000000Z"
     *       },
     *       "user": {
     *         "id": 335,
     *         "display_name": "b.koch",
     *         "role": "user",
     *         "created_at": "2025-07-09T17:26:47.000000Z",
     *         "updated_at": "2025-07-09T17:26:47.000000Z",
     *         "is_banned": null,
     *         "was_ever_banned": false,
     *         "moderation_info": []
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
     * Note: This endpoint requires admin or moderator privileges as it accesses all likes in the system.
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
     * @bodyParam likeable_id integer required The ID of the entity to like. Example: 324
     * 
     * @bodyContent {
     *   "likeable_type": "post",                   || required, string, must be 'post' or 'comment'
     *   "likeable_id": 324                         || required, integer
     * }
     *
     * @response status=201 scenario="Success" {
     *   "status": "success",
     *   "message": "Like added successfully",
     *   "code": 201,
     *   "count": 1,
     *   "data": {
     *     "user_id": 1,
     *     "likeable_id": 325,
     *     "likeable_type": "App\\Models\\Post",
     *     "type": "post",
     *     "updated_at": "2025-07-12T17:40:35.000000Z",
     *     "created_at": "2025-07-12T17:40:35.000000Z",
     *     "id": 4140
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

            /**
             * Map the likeable_type to the corresponding model class
             * This is required for polymorphic relationships in the database
             */
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

                // Create a new UserLike
                $like = new UserLike();

                $like->user_id = $user->id;
                $like->likeable_id = $likeableId;
                $like->likeable_type = $likeableType;
                $like->type = $simpleType;

                $like->save();

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
     * @bodyParam likeable_id integer required The ID of the entity to unlike. Example: 325
     * 
     * @bodyContent {
     *   "likeable_type": "post",                   || required, string, must be 'post' or 'comment'
     *   "likeable_id": 325                         || required, integer
     * }
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Like removed successfully",
     *   "code": 200,
     *   "count": 0,
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
     * List All Liked Posts of a User
     * 
     * Endpoint: GET /user-likes/{userId}/posts
     *
     * Retrieves a list of posts that have been liked by the specified user, with support for filtering, sorting, field selection, relation inclusion, and pagination.  
     * **By default, results are paginated.** 
     *
     * The relations `tags`, `languages`, and `technologies` are always included in the response and do not require the `include` parameter.
     * Other relations (e.g. `user`) can be included using the `include` parameter.
     *
     * You can use the `*_fields` parameter for all relations (e.g. `user_fields`, `tags_fields`, `languages_fields`, `technologies_fields`)
     * to specify which fields should be returned for each relation.
     * 
     * Example: `/user-likes/5/posts/?include=user&user_fields=id,display_name&tags_fields=name`
     *
     * @group User Likes
     *
     * @urlParam userId required The ID of the user whose liked posts should be retrieved. Example: 5
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
     * @queryParam include  See [ApiInclude](#apiinclude) for relation inclusion details (e.g. user). 
     * @see \App\Traits\ApiInclude::getRelationKeyFields()
     * 
     * @queryParam *_fields string See [ApiInclude](#apiinclude). When including a relation or for always-included relations (tags, languages, technologies), specify fields to return. Example: tags_fields=name
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest() for dynamic includes
     * @see \App\Traits\PostQuerySetup::getSelectRelationFields() for always-included relations
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
     * Example URL: /user-likes/5/posts
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Liked posts retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 42,
     *       "title": "Example Post Title",
     *       "code": "...",
     *       "description": "...",
     *       "images": [],                                  || Empty by default - requires user consent or owner access
     *       "videos": [                                    || Empty by default - requires user consent or owner access
     *         "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
     *         "https://www.youtube.com/watch?v=dQw4w9WgXcQ"
     *       ],                                
     *       "resources": [],                               || Empty by default - requires user consent or owner access
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
     *       "category": "Machine Learning",                || See /post-allowed-values/?filter[type]=category for valid values.
     *       "post_type": "Feedback",                       || See /post-allowed-values/?filter[type]=post_type for valid values.
     *       "status": "Published",                         || See /post-allowed-values/?filter[type]=status for valid values. 
     *       "favorite_count": 1,
     *       "likes_count": 0,
     *       "reports_count": 0,                            || Admin and Moderator only
     *       "comments_count": 0,
     *       "is_updated": false,
     *       "updated_by_role": null,
     *       "last_comment_at": null,
     *       "history": [],
     *       "moderation_info": [],                         || Admin and Moderator only
     *       "created_at": "2025-06-23T22:52:38.000000Z",
     *       "updated_at": "2025-06-23T22:53:53.000000Z",
     *       "is_favorited": false,                         || Virtual field, true if the authenticated user has favorited this post
     *       "is_liked": true,                              || Virtual field, true if the authenticated user has liked this post
     *       "tags": [                                      || See /post-allowed-values/?filter[type]=tag for valid values.
     *         { "id": 1, "name": "Laravel" },              || Note: Users can create new tags when posting; other allowed values are admin-only.
     *         { "id": 2, "name": "PHP" },
     *         { "id": 3, "name": "Backend" }
     *       ],
     *       "languages": [                                 || See /post-allowed-values/?filter[type]=language for valid values.
     *         { "id": 4, "name": "Java" },
     *         { "id": 5, "name": "C#" },
     *         { "id": 6, "name": "TypeScript" }
     *       ],
     *       "technologies": [                              || See /post-allowed-values/?filter[type]=technology for valid values.
     *         { "id": 7, "name": "Bootstrap" },
     *         { "id": 8, "name": "TailwindCSS" },
     *         { "id": 9, "name": "Material UI" }
     *       ]
     *     }
     *   ]
     * }
     * 
     * Example URL: /user-likes/5/posts/?include=user
     *
     * @response status=200 scenario="Success with user include" {
     *   "status": "success",
     *   "message": "Liked posts retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *      ..... || Same post data as above
     *       "user": {
     *          "id": 42,
     *          "display_name": "John Doe",
     *          "role": "user",
     *          "created_at": "2025-06-23T22:52:35.000000Z",
     *          "updated_at": "2025-06-23T22:52:35.000000Z",
     *          "is_banned": null,                      || Admin and Moderator only
     *          "was_ever_banned": false,               || Admin and Moderator only
     *          "moderation_info": [],                  || Admin and Moderator only
     *          "is_following": false                   || Virtual field, true if the authenticated user follows this user
     *        },
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
     * @response status=404 scenario="User not found" {
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
     * Note: External content (images, videos, resources) is not displayed by default for privacy reasons.
     * To view this content, one of the following conditions must be met:
     * 1. You are the owner of the post (automatically shows all content)
     * 2. For non-authenticated users: Send header X-Show-External-Images: true (similarly for videos/resources)
     * 3. For authenticated users: Either have auto_load_external_images set to true in user profile,
     *    or have a valid temporary permission (external_images_temp_until date is in the future)
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

            $originalSelectFields = $this->getSelectFields($request);

            $posts = $this->setupPostQuery($request, $query, 'buildQuery');
            if ($posts instanceof JsonResponse) {
                return $posts;
            }

            if ($posts->isEmpty()) {
                return $this->successResponse([], 'No liked posts found', 200);
            }

            $posts = $this->managePostsFieldVisibility($request, $posts);

            $posts = $this->checkForIncludedRelations($request, $posts);

            $posts = $this->controlVisibleFields($request, $originalSelectFields, $posts);

            $posts = $this->isFavorited($request, $user, $posts, $originalSelectFields);

            $posts = $this->isLiked($request, $user, $posts, 'post', $originalSelectFields);

            $posts = $this->isFollowing($request, $posts);

            return $this->successResponse($posts, 'Liked posts retrieved successfully', 200);
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
     * Retrieves all comments that have been liked by the specified user, with support for filtering, sorting, field selection, relation inclusion, and pagination.
     * **By default, results are paginated.**
     *
     * Only the `user` relation can be included via the `include` parameter.
     * You can use `user_fields` to specify which fields should be returned for the user relation.
     *
     * Example: `/user-likes/4/comments/?include=user&user_fields=id,display_name`
     *
     * @group User Likes
     *
     * @urlParam userId required The ID of the user whose liked comments should be retrieved. Example: 4
     *
     * @queryParam select   See [ApiSelectable](#apiselectable) for field selection details. Example: select=id,content,post_id
     * @see \App\Traits\ApiSelectable::select()
     * 
     * @queryParam sort     See [ApiSorting](#apisorting) for sorting details. Example: sort=-created_at
     * @see \App\Traits\ApiSorting::sort()
     * 
     * @queryParam filter   See [ApiFiltering](#apifiltering) for filtering details. Example: filter[post_id]=5
     * @see \App\Traits\ApiFiltering::filter()
     * 
     * @queryParam include  See [ApiInclude](#apiinclude) for relation inclusion details (only `user` is supported). Example: include=user
     * @see \App\Traits\ApiInclude::getRelationKeyFields()
     * 
     * @queryParam user_fields string See [ApiInclude](#apiinclude). When including user relation, specify fields to return. Example: user_fields=id,display_name
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest()
     * 
     * @queryParam page     Pagination, see [ApiPagination](#apipagination). Example: page=1
     * @see \App\Traits\ApiPagination::paginate()
     * 
     * @queryParam per_page Pagination, see [ApiPagination](#apipagination). Example: per_page=15
     * @see \App\Traits\ApiPagination::paginate()
     * 
     * @queryParam setLimit Disables pagination and limits the number of results. See [ApiLimit](#apilimit).
     * @see \App\Traits\ApiLimit::setLimit()
     *
     * Example URL: /user-likes/4/comments
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Liked comments retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 523,
     *       "post_id": 15,
     *       "user_id": 339,
     *       "parent_id": 508,
     *       "content": "This is a comment on the post",
     *       "parent_content": "This is the parent comment content",
     *       "is_deleted": false,
     *       "depth": 1,
     *       "likes_count": 3,
     *       "reports_count": 0,                                                    || Admin and Moderator only
     *       "is_updated": false,
     *       "updated_by_role": null,
     *       "moderation_info": [],                                                 || Admin and Moderator only
     *       "created_at": "2025-07-09T17:27:16.000000Z",
     *       "updated_at": "2025-07-12T17:41:00.000000Z",
     *       "is_liked": true
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
     *   "count": 1,
     *   "data": [
     *     {
     *    ..... || Same comment data as above
     *       "user": {
     *         "id": 339,
     *         "display_name": "Jane Doe",
     *         "role": "user",
     *         "created_at": "2025-07-09T17:26:47.000000Z",
     *         "updated_at": "2025-07-09T17:26:47.000000Z",
     *         "is_banned": null,                                                   || Admin and Moderator only
     *         "was_ever_banned": false,                                            || Admin and Moderator only
     *         "moderation_info": [],                                               || Admin and Moderator only
     *         "is_following": false                                                || Virtual field, true if the authenticated user is following this user
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
     * @response status=404 scenario="User not found" {
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

            $originalSelectFields = $this->getSelectFields($request);

            $comments = $this->setupCommentQuery($request, $query, 'buildQuery');
            if ($comments instanceof JsonResponse) {
                return $comments;
            }

            if ($comments->isEmpty()) {
                return $this->successResponse([], 'No liked comments found', 200);
            }

            $comments = $this->manageCommentsFieldVisibility($request, $comments);

            $comments = $this->checkForIncludedRelations($request, $comments);

            $comments = $this->controlVisibleFields($request, $originalSelectFields, $comments);

            $comments = $this->isLiked($request, $user, $comments, 'comment', $originalSelectFields);

            $comments = $this->isFollowing($request, $comments);

            return $this->successResponse($comments, 'Liked comments retrieved successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('User not found', 'USER_NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
