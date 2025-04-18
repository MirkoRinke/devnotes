<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\Post;

use App\Traits\AuthHelper; // example $user = $this->getUserFromToken($request);
use App\Traits\ApiResponses; // example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\ApiSorting;  // example $query = $this->sort(request(), $query, ['id', 'title', 'language', 'category', 'status']);
use App\Traits\ApiFiltering; // example $query = $this->filter(request(), $query, ['title', 'language', 'category', 'status']);
use App\Traits\ApiSelectable; // example $this->selectAttributes($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiPagination; // example $this->getPerPage($request, $query, 10);
use App\Traits\ApiInclude; // example $this->checkForIncludedRelations($request, $query);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);
use App\Traits\RelationLoader; // examples:
// - Single relation: $this->loadRelation($request, $query, 'user', 'user_id', ['id', 'display_name'])
// - Multiple relations: $this->loadRelations($request, $query, [
//     ['relation' => 'user', 'foreignKey' => 'user_id', 'columns' => ['id', 'display_name']],
//     ['relation' => 'post', 'foreignKey' => 'post_id', 'columns' => ['id', 'title']]
// ])
use App\Traits\PostFieldManager;

use App\Services\ModerationService;
use App\Services\ExternalSourceService;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Illuminate\Auth\Access\AuthorizationException;

class PostApiController extends Controller {
    /**
     *  The traits used in the controller
     */
    use ApiResponses,
        ApiSorting,
        ApiFiltering,
        ApiSelectable,
        ApiPagination,
        ApiInclude,
        QueryBuilder,
        AuthorizesRequests,
        RelationLoader,
        AuthHelper,
        PostFieldManager;


    /**
     * The services used in the controller
     */
    protected $moderationService;
    protected $externalSourceService;

    /**
     * Constructor to initialize the services
     */
    public function __construct(ModerationService $moderationService, ExternalSourceService $externalSourceService) {
        $this->moderationService = $moderationService;
        $this->externalSourceService = $externalSourceService;
    }

    /**
     * The validation rules for the Create method
     */
    public function getValidationRulesCreate(): array {
        $allowedPostValues = $this->getAllowedPostValues();

        $validationRulesCreate = [
            'title' => 'required|string|max:255',
            'code' => 'nullable|string',
            'description' => 'required|string',
            'images' => 'nullable|array',
            'images.*' => 'url|max:2048',
            'videos' => 'nullable|array',
            'videos.*' => 'url|max:2048',
            'resources' => 'nullable|array',
            'resources.*' => 'url|max:2048',
            'language' => 'required|array|min:1',
            'language.*' => ['required', 'string', 'in:' . implode(',', $allowedPostValues['postLanguage'])],
            'category' => ['required', 'string', 'in:' . implode(',', $allowedPostValues['postCategory'])],
            'post_type' => ['required', 'string', 'in:' . implode(',', $allowedPostValues['postType'])],
            'tags' => 'required|array',
            'status' => ['required', 'string', 'in:' . implode(',', $allowedPostValues['postStatus'])],
        ];
        return $validationRulesCreate;
    }

    /**
     * The validation rules for the Update method
     */
    public function getValidationRulesUpdate(): array {
        $allowedPostValues = $this->getAllowedPostValues();

        $validationRulesUpdate = [
            'title' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|nullable|string',
            'description' => 'sometimes|required|string',
            'images' => 'sometimes|nullable|array',
            'images.*' => 'sometimes|url|max:2048',
            'videos' => 'sometimes|nullable|array',
            'videos.*' => 'sometimes|url|max:2048',
            'resources' => 'sometimes|nullable|array',
            'resources.*' => 'sometimes|url|max:2048',
            'language' => 'sometimes|required|array|min:1',
            'language.*' => ['sometimes', 'required', 'string', 'in:' . implode(',', $allowedPostValues['postLanguage'])],
            'category' => ['sometimes', 'required', 'string', 'in:' . implode(',', $allowedPostValues['postCategory'])],
            'post_type' => ['sometimes', 'required', 'string', 'in:' . implode(',', $allowedPostValues['postType'])],
            'tags' => 'sometimes|required|array',
            'status' => ['sometimes', 'required', 'string', 'in:' . implode(',', $allowedPostValues['postStatus'])],
        ];
        return $validationRulesUpdate;
    }


    /**
     * Setup the post query
     * This method is used to set up the query for the post
     * It applies the access filters.
     * It applies sorting, filtering, selecting, and pagination
     * It also loads the relations for the post
     * 
     * @param Request $request
     * @param $query
     * @param $methods
     * @return mixed
     */
    protected function setupPostQuery(Request $request, $query, $methods): mixed {

        $query = $this->applyAccessFilters($request, $query);

        $relationKeyFields = $this->getRelationKeyFields($request, ['user' => 'user_id']);

        $this->modifyRequestSelect($request, [...['id'], ...$relationKeyFields]);

        $query = $this->loadRelations($request, $query, [
            ['relation' => 'user', 'foreignKey' => 'user_id', 'columns' => $this->getRelationFieldsFromRequest($request, 'user')],

        ]);

        $query = $this->$methods($request, $query, 'post');
        if ($query instanceof JsonResponse) {
            return $query;
        }

        return $query;
    }


    /**
     * Display a listing of the resource.
     */
    public function index(Request $request) {
        try {
            if (Post::count() === 0) {
                return $this->successResponse([], 'No posts exist in the database', 200);
            }

            $query = Post::query();

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupPostQuery($request, $query, 'buildQuery');

            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No posts found with the given filters', 200);
            }

            $query = $this->manageFieldVisibility($request, $query);

            $query = $this->checkForIncludedRelations($request, $query);

            $query = $this->controlVisibleFields($request, $originalSelectFields, $query);


            return $this->successResponse($query, 'Posts retrieved successfully');
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse {
        try {
            $validatedData = $request->validate(
                $this->getValidationRulesCreate(),
                $this->getValidationMessages()
            );

            $validatedData['user_id'] = $request->user()->id;

            // Create the external_source_previews field
            if (array_key_exists('images', $validatedData) || array_key_exists('resources', $validatedData)) {
                $validatedData['external_source_previews'] = $this->externalSourceService->generatePreviews([
                    'images' => $validatedData['images'] ?? [],
                    'videos' => $validatedData['videos'] ?? [],
                    'resources' => $validatedData['resources'] ?? []
                ]);
            }

            $post = Post::create($validatedData);

            return $this->successResponse($post, 'Post created successfully', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, Request $request): JsonResponse {
        try {
            $query = Post::query()->where('id', $id);

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupPostQuery($request, $query, 'buildQuerySelect');
            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            $post = $query->firstOrFail();

            $post = $this->manageFieldVisibility($request, $post);

            $post = $this->checkForIncludedRelations($request, $post);

            $post = $this->controlVisibleFields($request, $originalSelectFields, $post);

            return $this->successResponse($post, 'Post retrieved successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("Post with ID $id does not exist", 'POST_NOT_FOUND', 404);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse {
        try {
            $post = Post::findOrFail($id);

            $this->authorize('update', $post);

            $validationRules = $this->getValidationRulesUpdate();

            /** 
             * Check if the user is an admin or moderator and if they are not the owner of the post
             * If so, add the moderation_reason to the validation rules
             */
            if ($request->user()->id !== $post->user_id && ($request->user()->role === 'admin' || $request->user()->role === 'moderator')) {
                $validationRules['moderation_reason'] = 'required|string|max:255';
            }

            $validatedData = $request->validate(
                $validationRules,
                $this->getValidationMessages()
            );

            // Create the external_source_previews field
            if (array_key_exists('images', $validatedData) || array_key_exists('resources', $validatedData)) {
                $validatedData['external_source_previews'] = $this->externalSourceService->generatePreviews([
                    'images' => $validatedData['images'] ?? $post->images ?? [],
                    'videos' => $validatedData['videos'] ?? $post->videos ?? [],
                    'resources' => $validatedData['resources'] ?? $post->resources ?? []
                ]);
            }

            /** 
             * Check if the user is an admin or moderator and if they are not the owner of the post
             * If so, handle the moderation update
             */
            if ($request->user()->id !== $post->user_id && ($request->user()->role === 'admin' || $request->user()->role === 'moderator')) {
                $post = $this->moderationService->handleModerationUpdate(
                    $post,
                    array_merge(
                        $validatedData,
                        [
                            'is_updated' => true,
                            'updated_by_role' => $request->user()->role // For show the user who updated the post for the user
                        ]
                    ),
                    $request,
                    ['title', 'code', 'description', 'images', 'resources', 'language', 'category', 'post_type', 'tags', 'status'],
                    'post'
                );
                $post->save();

                return $this->successResponse($post, 'Post updated successfully', 200);
            }

            $validatedData = array_merge(
                $validatedData,
                ['is_updated' => true],
                ['updated_by_role' => $request->user()->role]
            );

            $post->update($validatedData);

            $post = $this->manageFieldVisibility($request, $post);

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
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse {
        try {
            $post = Post::findOrFail($id);

            $this->authorize('delete', $post);

            $post->delete();

            return $this->successResponse(null, 'Post deleted successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("Post with ID $id does not exist", 'POST_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Get Interactions for a User's Posts
     * 
     * This method calculates the total count of likes or favorites
     * that a user has received across all of their posts.
     *
     */
    public function getUserPostsInteractions(Request $request, string $id) {
        try {

            $validatedData = $request->validate(
                [
                    'type' => 'required|string|in:likes_count,favorite_count',
                ],
                $this->getValidationMessages()
            );

            $total = (int)Post::where('user_id', $id)->sum($validatedData['type']);

            return $this->successResponse($total, 'Total ' . $validatedData['type'] . ' for user with ID ' . $id, 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse("User with ID $id does not exist", 'USER_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
