<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\Post;

use App\Traits\ApiResponses; // example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\ApiSorting;  // example $query = $this->sort(request(), $query, ['id', 'title', 'language', 'category', 'status']);
use App\Traits\ApiFiltering; // example $query = $this->filter(request(), $query, ['title', 'language', 'category', 'status']);
use App\Traits\ApiSelectable; // example $this->selectAttributes($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiPagination; // example $this->getPerPage($request, $query, 10);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);
use App\Traits\RelationLoader; // examples:
// - Single relation: $this->loadRelation($request, $query, 'user', 'user_id', ['id', 'display_name'])
// - Multiple relations: $this->loadRelations($request, $query, [
//     ['relation' => 'user', 'foreignKey' => 'user_id', 'columns' => ['id', 'display_name']],
//     ['relation' => 'post', 'foreignKey' => 'post_id', 'columns' => ['id', 'title']]
// ])

use App\Services\ModerationService;
use App\Services\ExternalSourceService;

use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Sanctum\PersonalAccessToken;

class PostApiController extends Controller {
    /**
     *  The traits used in the controller
     */
    use ApiResponses, ApiSorting, ApiFiltering, ApiSelectable, ApiPagination, QueryBuilder, AuthorizesRequests, RelationLoader;


    protected $moderationService;
    protected $externalSourceService;

    public function __construct(ModerationService $moderationService, ExternalSourceService $externalSourceService) {
        $this->moderationService = $moderationService;
        $this->externalSourceService = $externalSourceService;
    }

    /**
     * The validation rules for the Create method
     */
    private $validationRulesCreate = [
        'title' => 'required|string|max:255',
        'code' => 'required|string',
        'description' => 'required|string',
        'images' => 'nullable|array',
        'images.*' => 'url|max:2048',
        'resources' => 'nullable|array',
        'resources.*' => 'url|max:2048',
        'language' => 'required|string|max:50',
        'category' => 'required|string|max:50',
        'tags' => 'required|array',
        'status' => 'required|in:draft,published,archived',
    ];


    /**
     * The validation rules for the Update method
     */
    private $validationRulesUpdate = [
        'title' => 'sometimes|required|string|max:255',
        'code' => 'sometimes|required|string',
        'description' => 'sometimes|required|string',
        'images' => 'sometimes|nullable|array',
        'images.*' => 'sometimes|url|max:2048',
        'resources' => 'sometimes|nullable|array',
        'resources.*' => 'sometimes|url|max:2048',
        'language' => 'sometimes|required|string|max:50',
        'category' => 'sometimes|required|string|max:50',
        'tags' => 'sometimes|required|array',
        'status' => 'sometimes|required|in:draft,published,archived',
    ];


    /**
     * 
     * Extract user from the authorization bearer token
     * 
     * @param Request $request The HTTP request containing the bearer token
     * @return mixed|null Returns the authenticated user model instance if 
     *                    a valid token exists, null otherwise
     */
    private function getUserFromToken(Request $request) {
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            return null;
        }

        $token = PersonalAccessToken::findToken($bearerToken);

        return $token ? $token->tokenable->load('profile') : null;
    }

    /**
     * Apply access filters to query based on user role
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyAccessFilters(Request $request, $query) {
        $user = $this->getUserFromToken($request);
        if (!$user) {
            $query->where('status', 'published')->where('reports_count', '<', 5);
        } elseif ($user->role !== 'admin' && $user->role !== 'moderator') {
            $query->where(function ($subQuery) use ($user) {
                $subQuery->where('user_id', $user->id)->orWhere(function ($subsubQuery) {
                    $subsubQuery->where('status', 'published')->where('reports_count', '<', 5);
                });
            });
        }
        return $query;
    }


    /**
     * Hide fields based on user role
     * 
     * @param Request $request The HTTP request containing the bearer token
     * @param \Illuminate\Database\Eloquent\Builder $query The query builder instance
     */
    public function hiddenFields(Request $request, $query) {
        $user = $this->getUserFromToken($request);

        if (!$user || ($user->role !== 'admin' && $user->role !== 'moderator')) {
            $query->makeHidden('moderation_info');
        }

        if (!$this->externalSourceService->shouldDisplayExternalImages($request, $user)) {
            $query->makeHidden('images');
        }
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

            $query = $this->applyAccessFilters($request, $query);

            $query = $this->loadRelation($request, $query, 'user', 'user_id', ['id', 'display_name']);

            $query = $this->buildQuery($request, $query, 'post');

            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse($query, 'No posts found with the given filters', 200);
            }

            $this->hiddenFields($request, $query);

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
                $this->validationRulesCreate,
                $this->getValidationMessages()
            );

            $validatedData['user_id'] = $request->user()->id;

            // Create the external_source_previews field
            if (array_key_exists('images', $validatedData) || array_key_exists('resources', $validatedData)) {
                $validatedData['external_source_previews'] = $this->externalSourceService->generatePreviews([
                    'images' => $validatedData['images'] ?? [],
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

            $query = $this->applyAccessFilters($request, $query);

            $query = $this->loadRelation($request, $query, 'user', 'user_id', ['id', 'display_name']);

            $query = $this->buildQuerySelect($request, $query, 'post');

            if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
                return $query;
            }

            $post = $query->firstOrFail();

            $this->hiddenFields($request, $post);

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

            /** 
             * Check if the user is an admin or moderator and if they are not the owner of the post
             * If so, add the moderation_reason to the validation rules
             */
            if ($request->user()->id !== $post->user_id && ($request->user()->role === 'admin' || $request->user()->role === 'moderator')) {
                $this->validationRulesUpdate['moderation_reason'] = 'required|string|max:255';
            }

            $validatedData = $request->validate(
                $this->validationRulesUpdate,
                $this->getValidationMessages()
            );

            // Create the external_source_previews field
            if (array_key_exists('images', $validatedData) || array_key_exists('resources', $validatedData)) {
                $validatedData['external_source_previews'] = $this->externalSourceService->generatePreviews([
                    'images' => $validatedData['images'] ?? $post->images ?? [],
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
                    ['title', 'code', 'description', 'images', 'resources', 'language', 'category', 'tags', 'status'],
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

            $this->hiddenFields($request, $post);

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
    public function destroy(string $id): JsonResponse {
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
}
