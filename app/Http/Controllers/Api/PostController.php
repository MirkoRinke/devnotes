<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use App\Models\Post;
use App\Models\PostAllowedValue;

use App\Rules\SafeUrl;
use App\Rules\ValidPostValue;

use App\Traits\ApiResponses;
use App\Traits\ApiInclude;
use App\Traits\QueryBuilder;
use App\Traits\RelationLoader;
use App\Traits\FieldManager;
use App\Traits\PostQuerySetup;
use App\Traits\FavoriteHelper;
use App\Traits\LikeHelper;
use App\Traits\FollowerHelper;
use App\Traits\PostAttributeRelationManager;

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
    use ApiResponses, QueryBuilder, ApiInclude, RelationLoader, FieldManager, AuthorizesRequests, PostQuerySetup, FavoriteHelper, LikeHelper, FollowerHelper, PostAttributeRelationManager;


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
            'language' => 'required|array|min:1',
            'language.*' => ['required', new ValidPostValue('language')],
            'category' => ['required', 'string', new ValidPostValue('category')],
            'post_type' => ['required', 'string', new ValidPostValue('post_type')],
            'technology' => 'required|array|min:1',
            'technology.*' => ['required', 'string', new ValidPostValue('technology')],
            'tags' => 'required|array',
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
            'language' => 'sometimes|required|array|min:1',
            'language.*' => ['sometimes', 'required', new ValidPostValue('language')],
            'category' => ['sometimes', 'required', 'string', new ValidPostValue('category')],
            'post_type' => ['sometimes', 'required', 'string', new ValidPostValue('post_type')],
            'technology' => 'sometimes|required|array|min:1',
            'technology.*' => ['sometimes', 'required', 'string', new ValidPostValue('technology')],
            'tags' => 'sometimes|required|array',
            'status' => ['sometimes', 'required', 'string', new ValidPostValue('status')],
        ];
        return $validationRulesUpdate;
    }


    /**
     * Generate external source previews for post data
     * 
     * @param array $validatedData The validated post data
     * @param Post|null $existingPost Existing post for fallback values (null for creation)
     * @return array The generated external source previews
     */
    protected function generateExternalSourcePreviews(array $validatedData, ?Post $existingPost = null): array {
        if (array_key_exists('images', $validatedData) || array_key_exists('resources', $validatedData) || array_key_exists('videos', $validatedData)) {
            $externalSourcePreviews = $this->externalSourceService->generatePreviews([
                'images' => $validatedData['images'] ?? $existingPost?->images ?? [],
                'videos' => $validatedData['videos'] ?? $existingPost?->videos ?? [],
                'resources' => $validatedData['resources'] ?? $existingPost?->resources ?? []
            ]);

            return $externalSourcePreviews;
        }

        return $existingPost->external_source_previews ?? [];
    }


    /**
     * List All Posts
     * 
     * Endpoint: GET /posts
     *
     * Retrieves a list of posts with support for filtering, sorting, field selection,
     * and relation inclusion. Results are filtered based on user permissions.
     *
     * @group Posts
     *
     * @queryParam select string Comma-separated fields to include. Example: select=id,title,user_id
     * @queryParam sort string Sort by field. Prefix with - for descending order. Example: sort=-created_at
     * @queryParam filter[category] string Filter posts by category. Example: filter[category]=Frontend
     * 
     * @queryParam startsWith string Filter where field starts with given string. Format: field:value. Example: startsWith[title]:Svelte
     * @queryParam endsWith string Filter where field ends with given string. Format: field:value. Example: endsWith[title]:Management
     * 
     * @queryParam include string Comma-separated relations to include. Example: include=user
     * @queryParam user_fields string When including user relation, specify fields to return. Example: user_fields=id,display_name
     * 
     * @queryParam page number The page number. Example: page=1
     * @queryParam per_page number Items per page. Example: per_page=15 (default: 10)
     * 
     * Example URL: /posts
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Posts retrieved successfully",
     *   "code": 200,
     *   "count": 11,
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 1,
     *       "title": "Svelte Store: Simple State Management",
     *       "code": "import { writable } from 'svelte/store';",
     *       "description": "Svelte Store is a simple and efficient way to manage state in Svelte applications. It allows you to create reactive variables that can be shared across components.",
     *       "images": [],              || Empty by default - requires user consent or owner access
     *       "videos": [],              || Empty by default - requires user consent or owner access
     *       "resources": [],           || Empty by default - requires user consent or owner access
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
     *       "language": ["HTML", "JavaScript"],
     *       "category": "Frontend",
     *       "post_type": "tutorial",
     *       "technology": ["Svelte"],
     *       "tags": ["svelte", "store", "state-management"],
     *       "status": "published",
     *       "favorite_count": 3,
     *       "likes_count": 0,
     *       "reports_count": 0,            || Admin and Moderator only
     *       "comments_count": 2,
     *       "is_updated": false,
     *       "updated_by_role": null,
     *       "last_comment_at": "2025-05-04T22:00:44.000000Z",
     *       "history": null,
     *       "moderation_info": null        || Admin and Moderator only
     *       "created_at": "2025-05-04T22:00:44.000000Z",
     *       "updated_at": "2025-05-04T22:00:45.000000Z",
     *     }
     *   ]
     * }
     * 
     * Example URL: /posts/?select=title&include=user&user_fields=id,display_name
     * 
     * @response status=200 scenario="Success with select and include" {
     *   "status": "success",
     *   "message": "Posts retrieved successfully",
     *   "code": 200,
     *   "count": 11,
     *   "data": [
     *     {
     *       "id": 1,
     *       "title": "Svelte Store: Einfaches State Management",
     *       "user": {
     *         "id": 1,
     *         "display_name": "admin"
     *       }
     *     }
     *   ]
     * }
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
     * 
     * @authenticated
     */
    public function index(Request $request) {
        try {
            if (Post::count() === 0) {
                return $this->successResponse([], 'No posts exist in the database', 200);
            }

            $user = $this->getAuthenticatedUser($request);

            $query = Post::query();

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupPostQuery($request, $query, 'buildQuery');
            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No posts found with the given filters', 200);
            }

            $query = $this->managePostsFieldVisibility($request, $query);

            $query = $this->checkForIncludedRelations($request, $query);

            $query = $this->controlVisibleFields($request, $originalSelectFields, $query);

            $query = $this->isFavorited($request, $user, $query, $originalSelectFields);

            $query = $this->isLiked($request, $user, $query, 'post', $originalSelectFields);

            $query = $this->isFollowing($request, $query);

            return $this->successResponse($query, 'Posts retrieved successfully');
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
     * @group Posts
     *
     * @bodyParam title string required The title of the post. Example: "Understanding JavaScript Promises"
     * @bodyParam code string The code snippet to include in the post. Example: "const promise = new Promise((resolve, reject) => {});"
     * @bodyParam description string required Description of the post. Example: "A comprehensive guide to JavaScript Promises"
     * 
     * @bodyParam images array Optional array of image URLs. Example: ["https://example.com/image.jpg"]
     * @bodyParam videos array Optional array of video URLs. Example: ["https://youtube.com/watch?v=example"]
     * @bodyParam resources array Optional array of resource URLs. Example: ["https://mdn.io/promise"]
     * 
     * @bodyParam language array required Array of programming languages. Example: ["JavaScript"]
     * @bodyParam category string required Category of the post. Example: "Frontend"
     * @bodyParam post_type string required Type of the post. Example: "tutorial"
     * @bodyParam technology array required Array of technologies used. Example: ["Node.js"]
     * @bodyParam tags array required Array of tags for the post. Example: ["promises", "async", "javascript"]
     * @bodyParam status string required Publication status. Example: "published"
     * 
     * @bodyContent {
     *   "title": "Understanding JavaScript Promises",                      || required, string, max:255
     *   "code": "const promise = new Promise((resolve, reject) => {});",   || optional, string
     *   "description": "A comprehensive guide to JavaScript Promises",     || required, string
     *   "images": ["https://example.com/image.jpg"],                       || optional, array of URLs
     *   "videos": ["https://youtube.com/watch?v=example"],                 || optional, array of URLs
     *   "resources": ["https://mdn.io/promise"],                           || optional, array of URLs
     *   "language": ["JavaScript"],                                        || required, array, min:1, valid language values only
     *   "category": "Frontend",                                            || required, string, valid category value
     *   "post_type": "tutorial",                                           || required, string, valid post_type value
     *   "technology": ["Node.js"],                                         || required, array, min:1, valid technology values only
     *   "tags": ["promises", "async", "javascript"],                       || required, array
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
     *     "id": 12
     *     "user_id": 1,
     *     "title": "Understanding JavaScript Promises",
     *     "code": "const promise = new Promise((resolve, reject) => {});",
     *     "description": "A comprehensive guide to JavaScript Promises",
     *     "images": ["https://example.com/image.jpg"],
     *     "videos": ["https://youtube.com/watch?v=example"],
     *     "resources": ["https://mdn.io/promise"],
     *     "external_source_previews": [
     *       {
     *         "type": "images",
     *         "url": "https://example.com/image.jpg",
     *         "domain": "example.com"
     *       },
     *       {
     *         "type": "videos",
     *         "url": "https://youtube.com/watch?v=example",
     *         "domain": "youtube.com"
     *       },
     *       {
     *         "type": "resources",
     *         "url": "https://mdn.io/promise",
     *         "domain": "mdn.io"
     *       }
     *     ],
     *     "language": ["JavaScript"],
     *     "category": "Frontend",
     *     "post_type": "tutorial",
     *     "technology": ["Node.js"],
     *     "tags": ["promises", "async", "javascript"],
     *     "status": "published",
     *     "history": [],
     *     "updated_at": "2025-05-05T17:16:53.000000Z",
     *     "created_at": "2025-05-05T17:16:53.000000Z",
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
     *     "technology": ["TECHNOLOGY_FIELD_REQUIRED"],
     *     "tags": ["TAGS_FIELD_REQUIRED"],
     *     "status": ["STATUS_FIELD_REQUIRED"]
     *   }
     * }
     *
     * @response status=422 scenario="Invalid Post Value" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "category": ["VALUE_IS_FORBIDDEN"],
     *     "post_type": ["VALUE_IS_FORBIDDEN"],
     *     "status": ["VALUE_IS_FORBIDDEN"],
     *     "language.0": ["VALUE_IS_FORBIDDEN"],
     *     "technology.0": ["VALUE_IS_FORBIDDEN"],
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

            // Get the tags from the validated data and remove them from the main data array
            $tagNames = $validatedData['tags'] ?? [];
            $languageNames = $validatedData['language'] ?? [];
            $technologyNames = $validatedData['technology'] ?? [];
            unset($validatedData['tags']);
            unset($validatedData['language']);
            unset($validatedData['technology']);


            // Create a new post
            $post = new Post($validatedData);
            $post->user_id = $user->id;
            $post->history = [];
            $post->external_source_previews = $this->generateExternalSourcePreviews($validatedData);
            $post->save();

            // Sync the relations for the post
            $this->syncMultipleRelations($post, $user, [
                'tag' => $tagNames,
                'language' => $languageNames,
                'technology' => $technologyNames,
            ]);

            // Load the relations for the post
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
     * Retrieves a single post by its ID with support for field selection and relation inclusion.
     * External content visibility is controlled by user settings or explicit consent headers.
     *
     * @group Posts
     *
     * @urlParam id required The ID of the post to retrieve. Example: 1
     * 
     * @queryParam select string Comma-separated fields to include. Example: select=id,title,user_id
     * 
     * @queryParam include string Comma-separated relations to include. Example: include=user
     * @queryParam user_fields string When including user relation, specify fields to return. Example: user_fields=id,display_name
     * 
     * Example URL: /posts/1
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Post retrieved successfully",
     *   "code": 200,
     *   "data": {
     *     "id": 1,
     *     "user_id": 1,
     *     "title": "Svelte Store: Simple State Management",
     *     "code": "import { writable } from 'svelte/store';",
     *     "description": "Svelte Store is a simple and efficient way to manage state in Svelte applications. It allows you to create reactive variables that can be shared across components.",
     *     "images": [],                || Empty by default - requires user consent or owner access
     *     "videos": [],                || Empty by default - requires user consent or owner access
     *     "resources": [],             || Empty by default - requires user consent or owner access
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
     *     "language": ["HTML", "JavaScript"],
     *     "category": "Frontend",
     *     "post_type": "tutorial",
     *     "technology": ["Svelte"],
     *     "tags": ["svelte", "store", "state-management"],
     *     "status": "published",
     *     "favorite_count": 3,
     *     "likes_count": 0,
     *     "reports_count": 0,          || Admin and Moderator only
     *     "comments_count": 2,
     *     "is_updated": false,
     *     "updated_by_role": null,
     *     "last_comment_at": "2025-05-04T22:00:44.000000Z",
     *     "history": null,
     *     "moderation_info": null,     || Admin and Moderator only
     *     "created_at": "2025-05-04T22:00:44.000000Z",
     *     "updated_at": "2025-05-04T22:00:45.000000Z"
     *   }
     * }
     * 
     * Example URL with select and include: /posts/1/?select=title,description&include=user&user_fields=id,display_name
     * 
     * @response status=200 scenario="Success with select and include" {
     *   "status": "success",
     *   "message": "Post retrieved successfully",
     *   "code": 200,
     *   "data": {
     *     "id": 1,
     *     "title": "Svelte Store: Simple State Management",
     *     "description": "Svelte Store is a simple and efficient way to manage state in Svelte applications. It allows you to create reactive variables that can be shared across components.",
     *     "user": {
     *       "id": 1,
     *       "display_name": "admin"
     *     }
     *   }
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
     * 
     * @authenticated
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
     * When a post is updated, a history record is created to track changes.
     * Admin/moderator updates require a moderation_reason.
     *
     * @group Posts
     *
     * @urlParam id required The ID of the post to update. Example: 14
     * 
     * @bodyParam title string The title of the post. Example: "Understanding JavaScript Promises - Updated"
     * @bodyParam code string The code snippet to include in the post. "Example: const promise = new Promise((resolve, reject) => {});"
     * @bodyParam description string Description of the post. Example: "A comprehensive guide to JavaScript Promises!"
     * 
     * @bodyParam images array Array of image URLs. Example: ["https://example.com/image2.jpg"]
     * @bodyParam videos array Array of video URLs. Example: ["https://youtube.com/watch?v=example"]
     * @bodyParam resources array Array of resource URLs. Example: ["https://mdn.io/promise"]
     * 
     * @bodyParam language array Array of programming languages. Example: ["JavaScript"]
     * @bodyParam category string Category of the post. Example: "Frontend"
     * @bodyParam post_type string Type of the post. Example: "tutorial"
     * @bodyParam technology array Array of technologies used. Example: ["Node.js"]
     * @bodyParam tags array Array of tags for the post. Example: ["promises", "async", "javascript"]
     * @bodyParam status string Publication status. Example: "published"
     * 
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
     * Example URL: /posts/14
     * 
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Post updated successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *     "id": 14,
     *     "user_id": 1,
     *     "title": "Understanding JavaScript Promises",
     *     "code": "const promise = new Promise((resolve, reject) => {});",
     *     "description": "A comprehensive guide to JavaScript Promises!",
     *     "images": [],
     *     "videos": ["https://youtube.com/watch?v=example"],
     *     "resources": ["https://mdn.io/promise"],
     *     "external_source_previews": [
     *       {
     *         "type": "videos",
     *         "url": "https://youtube.com/watch?v=example",
     *         "domain": "youtube.com"
     *       },
     *       {
     *         "type": "resources",
     *         "url": "https://mdn.io/promise",
     *         "domain": "mdn.io"
     *       }
     *     ],
     *     "language": ["JavaScript"],
     *     "category": "Frontend",
     *     "post_type": "tutorial",
     *     "technology": ["Node.js"],
     *     "tags": ["promises", "async", "javascript"],
     *     "status": "published",
     *     "favorite_count": 0,
     *     "likes_count": 0,
     *     "reports_count": 0,          || Admin and Moderator only
     *     "comments_count": 0,
     *     "is_updated": true,
     *     "updated_by_role": "admin",
     *     "last_comment_at": null,
     *     "history": [
     *       {
     *         "user_id": 1,
     *         "title": "Understanding JavaScript Promises",
     *         "code": "const promise = new Promise((resolve, reject) => {});",
     *         "description": "A comprehensive guide to JavaScript Promises",
     *         "images": ["https://example.com/image.jpg"],
     *         "videos": ["https://youtube.com/watch?v=example"],
     *         "resources": ["https://mdn.io/promise"],
     *         "external_source_previews": [
     *           {
     *             "url": "https://example.com/image.jpg",
     *             "type": "images",
     *             "domain": "example.com"
     *           },
     *           {
     *             "url": "https://youtube.com/watch?v=example",
     *             "type": "videos",
     *             "domain": "youtube.com"
     *           },
     *           {
     *             "url": "https://mdn.io/promise",
     *             "type": "resources",
     *             "domain": "mdn.io"
     *           }
     *         ],
     *         "language": ["JavaScript"],
     *         "category": "Frontend",
     *         "post_type": "tutorial",
     *         "technology": ["Node.js"],
     *         "tags": ["promises", "async", "javascript"],
     *         "status": "published",
     *         "created_at": "2025-05-05T17:39:44.856399Z"
     *       }
     *     ],
     *     "moderation_info": null,         || Admin and Moderator only
     *     "created_at": "2025-05-04T17:32:42.000000Z",
     *     "updated_at": "2025-05-05T17:39:44.000000Z"
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
     *     "category": ["VALUE_IS_FORBIDDEN"],
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
     * if the post owner makes the update and the post wasn't updated within the last 2 hours.
     * When admin/moderators update a post they don't own, they must provide a moderation_reason.
     * 
     * @authenticated
     */
    public function update(Request $request, string $id): JsonResponse {
        try {
            $post = Post::findOrFail($id);

            $this->authorize('update', $post);

            $user = $request->user();

            $validationRules = $this->getValidationRulesUpdate();

            $isContentModeration = $user->id !== $post->user_id && ($user->role === 'admin' || $user->role === 'moderator');

            /** 
             * Check if the user is an admin or moderator and if they are not the owner of the post
             * If so, add the moderation_reason to the validation rules
             */
            if ($isContentModeration) {
                $validationRules['moderation_reason'] = 'required|string|max:255';
            }

            $validatedData = $request->validate(
                $validationRules,
                $this->getValidationMessages('Post')
            );


            // Check if at least one field is provided
            if (empty($validatedData)) {
                return $this->errorResponse('At least one field must be provided for update', 'NO_FIELDS_PROVIDED', 422);
            }

            /**
             * Get the tags from the validated data and remove them from the main data array
             * This is necessary because tags are handled separately.
             */
            $tagNames = [];
            $languageNames = [];
            $technologyNames = [];

            $relationChanges = [];

            if (isset($validatedData['tags'])) {
                $relationChanges['tags'] = $validatedData['tags'];
                $tagNames = $validatedData['tags'];
                unset($validatedData['tags']);
            }

            if (isset($validatedData['language'])) {
                $relationChanges['language'] = $validatedData['language'];
                $languageNames = $validatedData['language'];
                unset($validatedData['language']);
            }

            if (isset($validatedData['technology'])) {
                $relationChanges['technology'] = $validatedData['technology'];
                $technologyNames = $validatedData['technology'];
                unset($validatedData['technology']);
            }

            /** 
             * Check if the user is an admin or moderator and if they are not the owner of the post
             * If so, handle the moderation update
             */
            if ($isContentModeration) {
                // Update the post
                // Set the moderation_info field and apply all changes from validatedData to the model
                $post = $this->moderationService->handleModerationUpdate(
                    $post,
                    $validatedData,
                    $request,
                    ['title', 'code', 'description', 'images', 'resources', 'language', 'category', 'post_type', 'technology', 'status'],
                    'post',
                    ['tags' => 'name'],
                    $relationChanges
                );

                $post->is_updated = true;
                $post->updated_by_role = $user->role;
                $post->external_source_previews = $this->generateExternalSourcePreviews($validatedData, $post);

                DB::transaction(function () use ($post, $tagNames, $languageNames, $technologyNames, $user) {
                    $post->save();

                    // Sync the relations for the post
                    $this->syncMultipleRelations($post, $user, [
                        'tag' => $tagNames,
                        'language' => $languageNames,
                        'technology' => $technologyNames
                    ]);

                    return $post;
                });

                // Load the relations for the post
                $post->load(['tags:id,name', 'languages:id,name', 'technologies:id,name']);

                return $this->successResponse($post, 'Post updated successfully', 200);
            }

            // Update the post
            $post->fill($validatedData);

            $post->is_updated = true;
            $post->updated_by_role = $user->role;
            $post->history = $this->historyService->createPostHistory($post, $user->id);
            $post->external_source_previews = $this->generateExternalSourcePreviews($validatedData, $post);

            DB::transaction(function () use ($post, $tagNames, $languageNames, $technologyNames, $user) {
                $post->save();

                // Sync the relations for the post
                $this->syncMultipleRelations($post, $user, [
                    'tag' => $tagNames,
                    'language' => $languageNames,
                    'technology' => $technologyNames
                ]);

                return $post;
            });

            $post = $this->managePostsFieldVisibility($request, $post);

            // Load the relations for the post
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
     *   "count": 1,
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

            $post = DB::transaction(function () use ($post) {
                // Delete all comments associated with the post
                $this->postRelationService->deleteComments($post);

                // Delete all reports and likes associated with the post
                $this->postRelationService->deleteReports($post);
                $this->postRelationService->deleteLikes($post);

                /**
                 * Note: Favorites are automatically deleted through 
                 * database foreign key constraints (onDelete('cascade')) 
                 * and don't require explicit deletion here.
                 */

                // Delete the post
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
