<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use App\Models\Post;

use App\Rules\SafeUrl;
use App\Rules\ValidPostValue;

use App\Traits\ApiResponses;
use App\Traits\ApiInclude;
use App\Traits\QueryBuilder;
use App\Traits\FieldManager;
use App\Traits\PostQuerySetup;
use App\Traits\PostHelper;
use App\Traits\UserFavoriteHelper;
use App\Traits\UserLikeHelper;
use App\Traits\UserFollowerHelper;
use App\Traits\PostAttributeRelationManager;
use App\Traits\PostAllowedValueHelper;

use App\Services\ModerationService;
use App\Services\ExternalSourceService;
use App\Services\PostRelationService;
use App\Services\HistoryService;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Illuminate\Auth\Access\AuthorizationException;


class PostController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, QueryBuilder, ApiInclude, FieldManager, AuthorizesRequests, PostQuerySetup, PostHelper, UserFavoriteHelper, UserLikeHelper, UserFollowerHelper, PostAttributeRelationManager, PostAllowedValueHelper;


    /**
     * The services used in the controller
     */
    protected $moderationService;
    protected $externalSourceService;
    protected $postRelationService;
    protected $historyService;

    /**
     * Constructor to initialize the services
     */
    public function __construct(
        ModerationService $moderationService,
        ExternalSourceService $externalSourceService,
        PostRelationService $postRelationService,
        HistoryService $historyService
    ) {
        $this->moderationService = $moderationService;
        $this->externalSourceService = $externalSourceService;
        $this->postRelationService = $postRelationService;
        $this->historyService = $historyService;
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
            'title' => 'required|string|max:255',
            'code' => 'nullable|string',
            'description' => 'required|string',
            'images' => 'nullable|array',
            'images.*' => ['max:2048', new SafeUrl()],
            'videos' => 'nullable|array',
            'videos.*' => ['max:2048', new SafeUrl()],
            'resources' => 'nullable|array',
            'resources.*' => ['max:2048', new SafeUrl()],
            'languages' => 'nullable|array',
            'languages.*' => ['required', new ValidPostValue('language')],
            'category' => ['required', 'string', new ValidPostValue('category')],
            'post_type' => ['required', 'string', new ValidPostValue('post_type')],
            'technologies' => 'nullable|array',
            'technologies.*' => ['string', new ValidPostValue('technology')],
            'tags' => 'nullable|array',
            'tags.*' => ['string'],
            'status' => ['required', 'string', new ValidPostValue('status')],
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
            'title' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|nullable|string',
            'description' => 'sometimes|required|string',
            'images' => 'sometimes|nullable|array',
            'images.*' => ['sometimes', 'max:2048', new SafeUrl()],
            'videos' => 'sometimes|nullable|array',
            'videos.*' => ['sometimes', 'max:2048', new SafeUrl()],
            'resources' => 'sometimes|nullable|array',
            'resources.*' => ['sometimes', 'max:2048', new SafeUrl()],
            'languages' => 'sometimes|array',
            'languages.*' => ['sometimes', 'required', new ValidPostValue('language')],
            'category' => ['sometimes', 'required', 'string', new ValidPostValue('category')],
            'post_type' => ['sometimes', 'required', 'string', new ValidPostValue('post_type')],
            'technologies' => 'sometimes|array',
            'technologies.*' => ['sometimes', 'string', new ValidPostValue('technology')],
            'tags' => 'sometimes|array',
            'tags.*' => ['sometimes', 'string'],
            'status' => ['sometimes', 'required', 'string', new ValidPostValue('status')],
        ];
        return $validationRulesUpdate;
    }


    /**
     * List All Posts
     * 
     * Endpoint: GET /posts
     *
     * Retrieves a list of posts with support for filtering, sorting, field selection, relation inclusion, and pagination.  
     * **By default, results are paginated.** 
     *
     * The relations `tags`, `languages`, and `technologies` are always included in the response and do not require the `include` parameter.
     * Other relations (e.g. `user`) can be included using the `include` parameter.
     *
     * You can use the `*_fields` parameter for all relations (e.g. `user_fields`, `tags_fields`, `languages_fields`, `technologies_fields`)
     * to specify which fields should be returned for each relation.
     * 
     * Example: `/posts/?include=user&user_fields=id,display_name&tags_fields=name`
     *
     * @group Posts
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
     * Example URL: /posts
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Posts retrieved successfully",
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
     *       "comments_updated_at": null,
     *       "history": [],
     *       "moderation_info": [],                         || Admin and Moderator only
     *       "created_at": "2025-06-23T22:52:38.000000Z",
     *       "updated_at": "2025-06-23T22:53:53.000000Z",
     *       "is_favorited": false,                         || Virtual field, true if the authenticated user has favorited this post
     *       "is_liked": false,                             || Virtual field, true if the authenticated user has liked this post
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
     * Example URL: /posts/?include=user
     *
     * @response status=200 scenario="Success with user include" {
     *   "status": "success",
     *   "message": "Posts retrieved successfully",
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
     *
     * @response status=200 scenario="No posts found with filters" {
     *   "status": "success",
     *   "message": "No posts found with the given filters",
     *   "code": 200,
     *   "count": 0,
     *   "data": []
     * }
     *
     * @response status=200 scenario="Empty database" {
     *   "status": "success",
     *   "message": "No posts exist in the database",
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
     * Note: External content (images, videos, resources) is not displayed by default for privacy reasons.
     * To view this content, one of the following conditions must be met:
     * 1. You are the owner of the post (automatically shows all content)
     * 2. For non-authenticated users: Send header X-Show-External-Images: true (similarly for videos/resources)
     * 3. For authenticated users: Either have auto_load_external_images set to true in user profile,
     *    or have a valid temporary permission (external_images_temp_until date is in the future)
     */
    public function index(Request $request) {
        try {
            if (Post::count() === 0) {
                return $this->successResponse([], 'No posts exist in the database', 200);
            }

            $user = $this->getAuthenticatedUser($request);

            $query = Post::query();

            $originalSelectFields = $this->getSelectFields($request);

            $posts = $this->setupPostQuery($request, $query, 'buildQuery');
            if ($posts instanceof JsonResponse) {
                return $posts;
            }

            if ($posts->isEmpty()) {
                return $this->successResponse($posts, 'No posts found with the given filters', 200);
            }

            $posts = $this->managePostsFieldVisibility($request, $posts);

            $posts = $this->checkForIncludedRelations($request, $posts);

            $posts = $this->controlVisibleFields($request, $originalSelectFields, $posts);

            $posts = $this->isFavorited($request, $user, $posts, $originalSelectFields);

            $posts = $this->isLiked($request, $user, $posts, 'post', $originalSelectFields);

            $posts = $this->isFollowing($request, $posts);

            return $this->successResponse($posts, 'Posts retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Create a New Post
     * 
     * Endpoint: POST /posts
     *
     * Creates a new post with the provided data. All posts are associated with the
     * authenticated user who creates them. External URLs are automatically processed
     * to generate previews.
     *
     * The relations `tags`, `languages`, and `technologies` are always included in the response.
     * For fields like `category`, `post_type`, `status`, `language`, and `technology`, only allowed values can be used.
     * Allowed values can be retrieved via the `/post-allowed-values` endpoint. Only tags can be created by users during post creation; all other allowed values are managed by admins/moderators.
     *
     * @group Posts
     *
     * @bodyParam title string required The title of the post. Example: "Understanding JavaScript Promises"
     * @bodyParam code string The code snippet to include in the post. Example: "const promise = new Promise((resolve, reject) => {});"
     * @bodyParam description string required Description of the post. Example: "A comprehensive guide to JavaScript Promises"
     * @bodyParam images array Optional array of image URLs. Example: ["https://example.com/image.jpg"]
     * @bodyParam videos array Optional array of video URLs. Example: ["https://youtube.com/watch?v=example"]
     * @bodyParam resources array Optional array of resource URLs. Example: ["https://mdn.io/promise"]
     * @bodyParam languages array required Array of programming languages. Example: ["JavaScript"]
     * @bodyParam category string required Category of the post. Example: "Frontend"
     * @bodyParam post_type string required Type of the post. Example: "tutorial"
     * @bodyParam technologies array Optional. Array of technologies used. Example: ["Node.js"] / Example: []
     * @bodyParam tags array Optional. Array of tags for the post. Example: ["promises", "async", "javascript"] / Example: []
     * @bodyParam status string required Publication status. Example: "published"
     * 
     * @bodyContent {
     *   "title": "Understanding JavaScript Promises",                      || required, string, max:255
     *   "code": "const promise = new Promise((resolve, reject) => {});",   || optional, string
     *   "description": "A comprehensive guide to JavaScript Promises",     || required, string
     *   "images": ["https://example.com/image.jpg"],                       || optional, array of URLs
     *   "videos": ["https://youtube.com/watch?v=example"],                 || optional, array of URLs
     *   "resources": ["https://mdn.io/promise"],                           || optional, array of URLs
     *   "languages": ["JavaScript"],                                       || optional, array, valid language values only
     *   "category": "Frontend",                                            || required, string, valid category value
     *   "post_type": "tutorial",                                           || required, string, valid post_type value
     *   "technologies": ["Node.js"],                                       || optional, array, valid technology values only
     *   "tags": ["promises", "async", "javascript"],                       || optional, array
     *   "status": "published"                                              || required, string, valid status value
     * }
     * 
     * Example URL: /posts
     *
     * @response status=201 scenario="Success" {
     *   "status": "success",
     *   "message": "Post created successfully",
     *   "code": 201,
     *   "count": 1,
     *   "data": {
     *     "id": 501,
     *     "user_id": 1,
     *     "title": "Angular Understanding JavaScript Promises",
     *     "code": "const promise = new Promise((resolve, reject) => {});",
     *     "description": "A comprehensive guide to JavaScript Promises",
     *     "images": [
     *       "https://example.com/image.jpg",
     *       "https://example.com/image2.jpg",
     *     ],
     *     "videos": [
     *       "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
     *       "https://vimeo.com/123456789"
     *     ],
     *     "resources": [
     *       "https://mdn.io/promise",
     *       "https://github.com/tc39/proposal-promise-finally"
     *     ],
     *     "external_source_previews": [
     *       {
     *         "type": "images",
     *         "url": "https://example.com/image.jpg",
     *         "domain": "example.com"
     *       },
     *       {
     *         "type": "images",
     *         "url": "https://example.com/image2.jpg",
     *         "domain": "example.com"
     *       },
     *       {
     *         "type": "videos",
     *         "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
     *         "domain": "www.youtube.com"
     *       },
     *       {
     *         "type": "videos",
     *         "url": "https://vimeo.com/123456789",
     *         "domain": "vimeo.com"
     *       },
     *       {
     *         "type": "resources",
     *         "url": "https://mdn.io/promise",
     *         "domain": "mdn.io"
     *       },
     *       {
     *         "type": "resources",
     *         "url": "https://github.com/tc39/proposal-promise-finally",
     *         "domain": "github.com"
     *       }
     *     ],
     *     "category": "Frontend",                        || See /post-allowed-values/?filter[type]=category for valid values.
     *     "post_type": "tutorial",                       || See /post-allowed-values/?filter[type]=post_type for valid values.
     *     "status": "published",                         || See /post-allowed-values/?filter[type]=status for valid values.
     *     "history": [],
     *     "created_at": "2025-06-28T17:12:00.000000Z",
     *     "updated_at": "2025-06-28T17:12:00.000000Z",
     *     "tags": [                                      || See /post-allowed-values/?filter[type]=tag for valid values.
     *       { "id": 67, "name": "promises" },            || Note: Users can create new tags when posting; other allowed values are admin-only.
     *       { "id": 68, "name": "async" },
     *       { "id": 69, "name": "javascript" }
     *     ],
     *     "languages": [                                 || See /post-allowed-values/?filter[type]=language for valid values.
     *       { "id": 4, "name": "Java" },
     *       { "id": 32, "name": "Python" },
     *       { "id": 43, "name": "Shell" }
     *     ],
     *     "technologies": [                              || See /post-allowed-values/?filter[type]=technology for valid values.
     *       { "id": 7, "name": "Bootstrap" },
     *       { "id": 23, "name": "Angular" },
     *       { "id": 41, "name": "Node.js" }
     *     ]
     *   }
     * }
     *
     * @response status=422 scenario="Validation Error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "title": ["TITLE_FIELD_REQUIRED"],
     *     "description": ["DESCRIPTION_FIELD_REQUIRED"],
     *     "language": ["LANGUAGE_FIELD_REQUIRED"],
     *     "category": ["CATEGORY_FIELD_REQUIRED"],
     *     "post_type": ["POST_TYPE_FIELD_REQUIRED"],
     *     "status": ["STATUS_FIELD_REQUIRED"]
     *   }
     * }
     *
     * @response status=422 scenario="Invalid Post Value" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "category": ["FRONTEND_IS_FORBIDDEN"],
     *     "post_type": ["TUTORIAL_IS_FORBIDDEN"],
     *     "status": ["PUBLISHED_IS_FORBIDDEN"],
     *     "language.0": ["JAVA_IS_FORBIDDEN"],
     *     "technology.0": ["ANGULAR_IS_FORBIDDEN"]
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
     * Note: When creating a post with external URLs (images, videos, resources), these will be processed
     * to generate preview information that is stored in the external_source_previews field.
     * For allowed values of category, post_type, status, language, and technology, see the /post-allowed-values endpoint.
     * Only tags can be created by users; all other allowed values are managed by admins/moderators.
     *
     * @authenticated
     */
    public function store(Request $request): JsonResponse {
        try {
            $validatedData = $request->validate(
                $this->getValidationRulesCreate(),
                $this->getValidationMessages('Post')
            );

            $user = $request->user();

            /**
             * Get the tags from the validated data and remove them from the main data array
             */
            $tagNames = $validatedData['tags'] ?? [];
            $languageNames = $validatedData['languages'] ?? [];
            $technologyNames = $validatedData['technologies'] ?? [];
            unset($validatedData['tags']);
            unset($validatedData['languages']);
            unset($validatedData['technologies']);

            $post = new Post($validatedData);
            $post->user_id = $user->id;
            $post->history = [];
            $post->moderation_info = [];
            $post->external_source_previews = $this->generateExternalSourcePreviews($validatedData);

            DB::transaction(function () use ($post, $user, $tagNames, $languageNames, $technologyNames, $validatedData) {
                $post->save();

                $this->syncMultipleRelations($post, $user, [
                    'tag' => $tagNames,
                    'language' => $languageNames,
                    'technology' => $technologyNames,
                ]);

                $syncValidatedData = array_merge(
                    $validatedData,
                    [
                        'tag' => $tagNames,
                        'language' => $languageNames,
                        'technology' => $technologyNames,
                    ]
                );

                $this->syncPostAllowedValueCounts($syncValidatedData);
            });

            $post->load(['tags:id,name', 'languages:id,name', 'technologies:id,name']);

            return $this->successResponse($post, 'Post created successfully', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Get a Specific Post
     * 
     * Endpoint: GET /posts/{id}
     *
     * Retrieves a single post by its ID with support for filtering, field selection, relation inclusion, and dynamic field selection for relations.
     *
     * The relations `tags`, `languages`, and `technologies` are always included in the response and do not require the `include` parameter.
     * Other relations (e.g. `user`) can be included using the `include` parameter.
     *
     * You can use the `*_fields` parameter for all relations (e.g. `user_fields`, `tags_fields`, `languages_fields`, `technologies_fields`)
     * to specify which fields should be returned for each relation.
     * 
     * Example: `/posts/1?include=user&user_fields=id,display_name&tags_fields=name`
     *
     * @group Posts
     *
     * @urlParam id required The ID of the post to retrieve. Example: 1
     *
     * @queryParam select   See [ApiSelectable](#apiselectable) for field selection details. 
     * @see \App\Traits\ApiSelectable::select()
     * 
     * @queryParam include  See [ApiInclude](#apiinclude) for relation inclusion details (e.g. user). 
     * @see \App\Traits\ApiInclude::getRelationKeyFields()
     * 
     * @queryParam *_fields string See [ApiInclude](#apiinclude). When including a relation or for always-included relations (tags, languages, technologies), specify fields to return. Example: tags_fields=name
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest() for dynamic includes
     * @see \App\Traits\PostQuerySetup::getSelectRelationFields() for always-included relations
     *
     * Example URL: /posts/1
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Post retrieved successfully",
     *   "code": 200,
     *   "data": {
     *     "id": 1,
     *     "user_id": 42,
     *     "title": "Svelte Store: Simple State Management",
     *     "code": "import { writable } from 'svelte/store';",
     *     "description": "Svelte Store is a simple and efficient way to manage state in Svelte applications. It allows you to create reactive variables that can be shared across components.",
     *     "images": ["https://picsum.photos/200"],       || Empty by default - requires user consent or owner access
     *     "videos": [],                                  || Empty by default - requires user consent or owner access
     *     "resources": [],                               || Empty by default - requires user consent or owner access
     *     "external_source_previews": [
     *       {
     *         "url": "https://picsum.photos/200",
     *         "type": "images",
     *         "domain": "picsum.photos"
     *       },
     *       {
     *         "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
     *         "type": "videos",
     *         "domain": "www.youtube.com"
     *       },
     *       {
     *         "url": "https://svelte.dev/docs#run-time-store",
     *         "type": "resources",
     *         "domain": "svelte.dev"
     *       }
     *     ],
     *     "category": "Frontend",                        || See /post-allowed-values/?filter[type]=category for valid values.
     *     "post_type": "tutorial",                       || See /post-allowed-values/?filter[type]=post_type for valid values.
     *     "status": "published",                         || See /post-allowed-values/?filter[type]=status for valid values.
     *     "favorite_count": 3,
     *     "likes_count": 0,
     *     "reports_count": 0,                            || Admin and Moderator only
     *     "comments_count": 2,
     *     "is_updated": false,
     *     "updated_by_role": null,
     *     "comments_updated_at": "2025-05-04T22:00:44.000000Z",
     *     "history": [],
     *     "moderation_info": [],                         || Admin and Moderator only
     *     "created_at": "2025-05-04T22:00:44.000000Z",
     *     "updated_at": "2025-05-04T22:00:45.000000Z",
     *     "is_favorited": false,                         || Virtual field, true if the authenticated user has favorited this post
     *     "is_liked": false,                             || Virtual field, true if the authenticated user has liked this post
     *     "tags": [                                      || See /post-allowed-values/?filter[type]=tag for valid values. Users can create new tags when posting; other allowed values are admin-only.
     *       { "id": 1, "name": "svelte" },
     *       { "id": 2, "name": "store" },
     *       { "id": 3, "name": "state-management" }
     *     ],
     *     "languages": [                                 || See /post-allowed-values/?filter[type]=language for valid values.
     *       { "id": 1, "name": "HTML" },
     *       { "id": 2, "name": "JavaScript" }
     *     ],
     *     "technologies": [                              || See /post-allowed-values/?filter[type]=technology for valid values.
     *       { "id": 1, "name": "Svelte" }
     *     ]
     *   }
     * }
     * 
     * Example URL: /posts/1?include=user
     *
     * @response status=200 scenario="Success with user include" {
     *   "status": "success",
     *   "message": "Post retrieved successfully",
     *   "code": 200,
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
     * @response status=404 scenario="Not Found" {
     *   "status": "error",
     *   "message": "Post with ID 999 does not exist",
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
     * Note: External content (images, videos, resources) is not displayed by default for privacy reasons.
     * To view this content, one of the following conditions must be met:
     * 1. You are the owner of the post (automatically shows all content)
     * 2. For non-authenticated users: Send header X-Show-External-Images: true (similarly for videos/resources)
     * 3. For authenticated users: Either have auto_load_external_images set to true in user profile,
     *    or have a valid temporary permission (external_images_temp_until date is in the future)
     */
    public function show(string $id, Request $request): JsonResponse {
        try {
            $query = Post::query()->where('id', $id);

            $user = $this->getAuthenticatedUser($request);

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupPostQuery($request, $query, 'buildQuerySelect');
            if ($query instanceof JsonResponse) {
                return $query;
            }

            $post = $query->firstOrFail();

            $post = $this->managePostsFieldVisibility($request, $post);

            $post = $this->checkForIncludedRelations($request, $post);

            $post = $this->controlVisibleFields($request, $originalSelectFields, $post);

            $post = $this->isFavorited($request, $user, $post, $originalSelectFields);

            $post = $this->isLiked($request, $user, $post, 'post', $originalSelectFields);

            $post = $this->isFollowing($request, $post);

            return $this->successResponse($post, 'Post retrieved successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("Post with ID $id does not exist", 'POST_NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Update an Existing Post
     * 
     * Endpoint: PATCH /posts/{id}
     *
     * Updates a post with the provided data. The user must be the owner of the post or an admin/moderator.
     * When a post is updated, a history record is created to track changes – but only if the owner updates the post and the last change was at least 2 hours ago.
     * Admins/Moderators must provide a moderation_reason when updating a post they do not own.
     *
     * @group Posts
     *
     * @urlParam id required The ID of the post to update. Example: 501
     * 
     * @bodyParam title string The title of the post. Example: "Angular Understanding JavaScript Promises"
     * @bodyParam code string The code snippet to include in the post. Example: "const promise = new Promise((resolve, reject) => {});"
     * @bodyParam description string Description of the post. Example: "A comprehensive guide to JavaScript Promises"
     * @bodyParam images array Array of image URLs. Example: ["https://example.com/image.jpg"]
     * @bodyParam videos array Array of video URLs. Example: ["https://youtube.com/watch?v=example"]
     * @bodyParam resources array Array of resource URLs. Example: ["https://mdn.io/promise"]
     * @bodyParam languages array Array of programming languages. Example: ["JavaScript"]
     * @bodyParam category string Category of the post. Example: "Frontend"
     * @bodyParam post_type string Type of the post. Example: "tutorial"
     * @bodyParam technologies array Array of technologies used. Example: ["Node.js"] / Example: []
     * @bodyParam tags array Array of tags for the post. Example: ["promises", "async", "javascript"] / Example: []
     * @bodyParam status string Publication status. Example: "published"
     * @bodyParam moderation_reason string Admin/moderator only: Reason for moderation action. Example: "Fixed code formatting"
     * 
     * @bodyContent {
     *   "description": "A comprehensive guide to JavaScript Promises!",  || Only fields that need updating
     *   "images": []                                                     || Empty array to remove all images
     * }
     * 
     * @bodyContent {
     *   "description": "A comprehensive guide to JavaScript Promises!",  || Only fields that need updating
     *   "images": []                                                     || Empty array to remove all images
     *   "moderation_reason": "Fixed code formatting"                     || Admin and Moderator only
     * }
     * 
     * Example URL: /posts/501
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Post updated successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 501,
     *     "user_id": 1,
     *     "title": "Angular Understanding JavaScript Promises",
     *     "code": "const promise = new Promise((resolve, reject) => {});",
     *     "description": "A comprehensive guide to JavaScript Promises",
     *     "images": [
     *       "https://example.com/image.jpg",
     *       "https://example.com/image2.jpg"
     *     ],
     *     "videos": [
     *       "https://www.youtube.com/watch?v=dQr4w7WgXfQ",
     *       "https://vimeo.com/123456789"
     *     ],
     *     "resources": [
     *       "https://mdn.io/promise",
     *       "https://github.com/tc39/proposal-promise-finally"
     *     ],
     *     "external_source_previews": [
     *       {
     *         "type": "images",
     *         "url": "https://example.com/image.jpg",
     *         "domain": "example.com"
     *       },
     *       {
     *         "type": "images",
     *         "url": "https://example.com/image2.jpg",
     *         "domain": "example.com"
     *       },
     *       {
     *         "type": "videos",
     *         "url": "https://www.youtube.com/watch?v=dQr4w7WgXfQ",
     *         "domain": "www.youtube.com"
     *       },
     *       {
     *         "type": "videos",
     *         "url": "https://vimeo.com/123456789",
     *         "domain": "vimeo.com"
     *       },
     *       {
     *         "type": "resources",
     *         "url": "https://mdn.io/promise",
     *         "domain": "mdn.io"
     *       },
     *       {
     *         "type": "resources",
     *         "url": "https://github.com/tc39/proposal-promise-finally",
     *         "domain": "github.com"
     *       }
     *     ],
     *     "category": "Frontend",                        || See /post-allowed-values/?filter[type]=category for valid values.
     *     "post_type": "tutorial",                       || See /post-allowed-values/?filter[type]=post_type for valid values.
     *     "status": "published",                         || See /post-allowed-values/?filter[type]=status for valid values.
     *     "favorite_count": 0,
     *     "likes_count": 0,
     *     "reports_count": 0,                            || Admin and Moderator only
     *     "comments_count": 0,
     *     "is_updated": true,
     *     "updated_by_role": "admin",
     *     "comments_updated_at": null,
     *     "history": [
     *       {
     *         "user_id": 1,
     *         "title": "Angular Understanding JavaScript Promises",
     *         "code": "const promise = new Promise((resolve, reject) => {});",
     *         "description": "A comprehensive guide to JavaScript Promises",
     *         "images": [
     *           "https://example.com/image.jpg",
     *           "https://example.com/image2.jpg"
     *         ],
     *         "videos": [
     *           "https://www.youtube.com/watch?v=dQr4d&gfnQ",
     *           "https://vimeo.com/123456789"
     *         ],
     *         "resources": [
     *           "https://mdn.io/promise",
     *           "https://github.com/tc39/proposal-promise-finally"
     *         ],
     *         "external_source_previews": [
     *           {
     *             "type": "images",
     *             "url": "https://example.com/image.jpg",
     *             "domain": "example.com"
     *           },
     *           {
     *             "type": "images",
     *             "url": "https://example.com/image2.jpg",
     *             "domain": "example.com"
     *           },
     *           {
     *             "type": "videos",
     *             "url": "https://www.youtube.com/watch?v=dQr4d&gfnQ",
     *             "domain": "www.youtube.com"
     *           },
     *           {
     *             "type": "videos",
     *             "url": "https://vimeo.com/123456789",
     *             "domain": "vimeo.com"
     *           },
     *           {
     *             "type": "resources",
     *             "url": "https://mdn.io/promise",
     *             "domain": "mdn.io"
     *           },
     *           {
     *             "type": "resources",
     *             "url": "https://github.com/tc39/proposal-promise-finally",
     *             "domain": "github.com"
     *           }
     *         ],
     *         "category": "Frontend",
     *         "post_type": "tutorial",
     *         "status": "published",
     *         "created_at": "2025-06-28T19:33:32.000000Z",
     *         "updated_at": "2025-06-28T19:33:32.000000Z",
     *         "tags": [
     *           { "id": 29, "name": "JavaScript" },
     *           { "id": 71, "name": "promises" },
     *           { "id": 72, "name": "async" }
     *         ],
     *         "languages": [
     *           { "id": 4, "name": "Java" },
     *           { "id": 32, "name": "Python" },
     *           { "id": 43, "name": "Shell" }
     *         ],
     *         "technologies": [
     *           { "id": 7, "name": "Bootstrap" },
     *           { "id": 23, "name": "Angular" },
     *           { "id": 41, "name": "Node.js" }
     *         ],
     *         "history_created_at": "2025-06-28T19:33:36.168677Z"
     *       }
     *     ],
     *     "moderation_info": [                           || Admin and Moderator only
     *       {
     *         "user_id": 1,
     *         "username": "Admin",
     *         "role": "admin",
     *         "timestamp": "2025-06-28T22:01:46+02:00",
     *         "reason": "Einfach ein paar dinge geändert zwecks test",
     *         "action": "updated",
     *         "changes": {
     *           "videos": {
     *             "from": [
     *               "https://www.youtube.com/watch?v=dQr4w7WgXfQ",
     *               "https://vimeo.com/123456789"
     *             ],
     *             "to": [
     *               "https://www.youtube.com/watch?v=dQr4wWrXfQ",
     *               "https://vimeo.com/123456789"
     *             ]
     *           },
     *           "resources": {
     *             "from": [
     *               "https://mdn.io/promise",
     *               "https://github.com/tc39/proposal-promise-finally"
     *             ],
     *             "to": [
     *               "https://github.com/tc39/proposal-promise-finally"
     *             ]
     *           },
     *           "tags": {
     *             "from": [
     *               "JavaScript",
     *               "promises",
     *               "async"
     *             ],
     *             "to": [
     *               "promises",
     *               "async"
     *             ]
     *           }
     *         }
     *       }
     *     ],
     *     "created_at": "2025-06-28T17:12:00.000000Z",
     *     "updated_at": "2025-06-28T18:12:48.000000Z",
     *     "tags": [                                      || See /post-allowed-values/?filter[type]=tag for valid values.
     *       { "id": 29, "name": "JavaScript" },
     *       { "id": 71, "name": "promises" },
     *       { "id": 72, "name": "async" }
     *     ],
     *     "languages": [                                 || See /post-allowed-values/?filter[type]=language for valid values.
     *       { "id": 14, "name": "JavaScript" },
     *       { "id": 32, "name": "Python" },
     *       { "id": 43, "name": "Shell" }
     *     ],
     *     "technologies": [                              || See /post-allowed-values/?filter[type]=technology for valid values.
     *       { "id": 7, "name": "Bootstrap" },
     *       { "id": 23, "name": "Angular" },
     *       { "id": 41, "name": "Node.js" }
     *     ]
     *   }
     * }
     *
     * @response status=404 scenario="Post Not Found" {
     *   "status": "error",
     *   "message": "Post with ID 999 does not exist",
     *   "code": 404,
     *   "errors": "POST_NOT_FOUND"
     * }
     *
     * @response status=403 scenario="Unauthorized" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     *
     * @response status=422 scenario="No Fields Provided" {
     *   "status": "error",
     *   "message": "At least one field must be provided for update",
     *   "code": 422,
     *   "errors": "NO_FIELDS_PROVIDED"
     * }
     * 
     * @response status=422 scenario="Validation Error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "title": ["TITLE_MUST_BE_STRING"],
     *     "category": ["FRONTEND_IS_FORBIDDEN"],
     *     "moderation_reason": ["MODERATION_REASON_FIELD_REQUIRED"]
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
     * Note: When updating a post, a history record is created to track changes. This only happens 
     * if the post owner makes the update and the last change was at least 2 hours ago (configurable).
     * Admins/Moderators must provide a moderation_reason when updating a post they do not own.
     * 
     * @authenticated
     */
    public function update(Request $request, string $id): JsonResponse {
        try {
            $post = Post::findOrFail($id);

            $oldPost = clone $post;

            $this->authorize('update', $post);

            $user = $request->user();

            $validationRules = $this->getValidationRulesUpdate();

            $isContentModeration = $user->id !== $post->user_id && ($user->role === 'admin' || $user->role === 'moderator');

            if ($isContentModeration) {
                $validationRules['moderation_reason'] = 'required|string|max:255';
            }

            $validatedData = $request->validate(
                $validationRules,
                $this->getValidationMessages('Post')
            );

            if (empty($validatedData)) {
                return $this->errorResponse('At least one field must be provided for update', 'NO_FIELDS_PROVIDED', 422);
            }

            /**
             * Get the tags from the validated data and remove them from the main data array
             * This is necessary because tags are handled separately.
             */
            $tagNames = null;
            $languageNames = null;
            $technologyNames = null;

            $oldTagNames = null;
            $oldLanguageNames = null;
            $oldTechnologyNames = null;

            $relationChanges = [];

            if (isset($validatedData['tags'])) {
                $relationChanges['tags'] = $validatedData['tags'];
                $tagNames = $validatedData['tags'];
                $oldTagNames = $post->tags->pluck('name')->toArray();
                unset($validatedData['tags']);
            }

            if (isset($validatedData['languages'])) {
                $relationChanges['languages'] = $validatedData['languages'];
                $languageNames = $validatedData['languages'];
                $oldLanguageNames = $post->languages->pluck('name')->toArray();
                unset($validatedData['languages']);
            }

            if (isset($validatedData['technologies'])) {
                $relationChanges['technologies'] = $validatedData['technologies'];
                $technologyNames = $validatedData['technologies'];
                $oldTechnologyNames = $post->technologies->pluck('name')->toArray();
                unset($validatedData['technologies']);
            }

            $oldRelations = [
                'tags' => $oldTagNames,
                'languages' => $oldLanguageNames,
                'technologies' => $oldTechnologyNames
            ];

            if ($isContentModeration) {
                /**
                 * Update the post and set the moderation_info field and apply all changes from validatedData to the model
                 */
                $post = $this->moderationService->handleModerationUpdate(
                    $post,
                    $validatedData,
                    $request,
                    ['title', 'code', 'description', 'videos', 'images', 'resources', 'category', 'post_type', 'status'],
                    'post',
                    ['tags' => 'name', 'languages' => 'name', 'technologies' => 'name'],
                    $relationChanges
                );

                $post->is_updated = true;
                $post->updated_by_role = $user->role;
                $post->external_source_previews = $this->generateExternalSourcePreviews($validatedData, $post);

                DB::transaction(function () use ($post, $tagNames, $languageNames, $technologyNames, $user, $validatedData, $oldPost, $oldRelations) {
                    $post->save();

                    $this->syncMultipleRelations($post, $user, [
                        'tag' => $tagNames,
                        'language' => $languageNames,
                        'technology' => $technologyNames,
                    ]);

                    $syncValidatedData = array_merge(
                        $validatedData,
                        [
                            'tag' => $tagNames,
                            'language' => $languageNames,
                            'technology' => $technologyNames,
                        ]
                    );
                    $this->syncPostAllowedValueCounts($syncValidatedData, $oldPost, $oldRelations);


                    return $post;
                });

                $post = $this->managePostsFieldVisibility($request, $post);

                $post->load(['tags:id,name', 'languages:id,name', 'technologies:id,name']);

                return $this->successResponse($post, 'Post updated successfully', 200);
            }

            $post->fill($validatedData);
            $post->is_updated = true;
            $post->updated_by_role = $user->role;
            $post->history = $this->historyService->createPostHistory($post, $user->id);
            $post->external_source_previews = $this->generateExternalSourcePreviews($validatedData, $post);

            DB::transaction(function () use ($post, $tagNames, $languageNames, $technologyNames, $user, $validatedData, $oldPost, $oldRelations) {
                $post->save();

                $this->syncMultipleRelations($post, $user, [
                    'tag' => $tagNames,
                    'language' => $languageNames,
                    'technology' => $technologyNames,
                ]);

                $syncValidatedData = array_merge(
                    $validatedData,
                    [
                        'tag' => $tagNames,
                        'language' => $languageNames,
                        'technology' => $technologyNames,
                    ]
                );

                $this->syncPostAllowedValueCounts($syncValidatedData, $oldPost, $oldRelations);

                return $post;
            });

            $post = $this->managePostsFieldVisibility($request, $post);

            $post->load(['tags:id,name', 'languages:id,name', 'technologies:id,name']);

            return $this->successResponse($post, 'Post updated successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("Post with ID $id does not exist", 'POST_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Delete a Post
     * 
     * Endpoint: DELETE /posts/{id}
     *
     * Permanently removes a post and all of its associated data including comments, 
     * reports, and likes. Favorites are automatically deleted through database constraints.
     * Only the post owner or admin/moderator can delete a post.
     *
     * @group Posts
     *
     * @urlParam id required The ID of the post to delete. Example: 14
     * 
     * Example URL: /posts/14
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Post deleted successfully",
     *   "code": 200,
     *   "count": 0,
     *   "data": null
     * }
     *
     * @response status=404 scenario="Post Not Found" {
     *   "status": "error",
     *   "message": "Post with ID 999 does not exist",
     *   "code": 404,
     *   "errors": "POST_NOT_FOUND"
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
     * Note: This action is permanent and cannot be undone. All associated data
     * including comments, reports, and likes will also be permanently removed.
     * 
     * @authenticated
     */
    public function destroy(Request $request, string $id): JsonResponse {
        try {
            $post = Post::findOrFail($id);

            $this->authorize('delete', $post);

            DB::transaction(function () use ($post) {
                $this->postRelationService->deleteComments($post);

                $this->postRelationService->deleteReports($post);
                $this->postRelationService->deleteLikes($post);

                /**
                 * Note: Favorites are automatically deleted through 
                 * database foreign key constraints (onDelete('cascade')) 
                 * and don't require explicit deletion here.
                 */

                $this->destroyPostAllowedValueCounts($post);

                $post->delete();
            });

            return $this->successResponse(null, 'Post deleted successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("Post with ID $id does not exist", 'POST_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
