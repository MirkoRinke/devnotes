<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\Like;
use App\Models\Post;
use App\Models\Comment;

use App\Traits\ApiResponses; // example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\ApiSorting;  // example $query = $this->sort(request(), $query, ['id', 'title', 'language', 'category', 'status']);
use App\Traits\ApiFiltering; // example $query = $this->filter(request(), $query, ['title', 'language', 'category', 'status']);
use App\Traits\ApiSelectable; // example $this->selectAttributes($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiPagination; // example $this->getPerPage($request, $query, 10);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);
use App\Traits\RelationLoader; // example $this->loadRelationIfNeeded($request, $query, 'user', 'user_id', ['id', 'name']);

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LikeController extends Controller {

    /**
     *  The traits used in the controller
     */
    use AuthorizesRequests, ApiResponses, ApiSorting, ApiFiltering, ApiSelectable, ApiPagination, QueryBuilder, RelationLoader;

    /**
     * The validation rule for the like entity
     */
    private $validationRules = [
        'likeable_type' => 'required|in:post,comment',
        'likeable_id' => 'required|integer',
    ];

    /**
     * Update the likes_count for a likeable entity
     */
    private function updateLikesCount($likeable, $method = 'increment') {
        $likeable->$method('likes_count');
    }

    /**
     * Get all likes
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request) {
        try {
            $this->authorize('viewAny', Like::class);

            $query = Like::query();

            if ($request->has('include')) {
                $includes = explode(',', $request->input('include'));
                $allowedIncludes = ['user', 'likeable'];
                $validIncludes = array_intersect($allowedIncludes, $includes);

                if (!empty($validIncludes)) {
                    $query->with($validIncludes);
                }
            }

            $query = $this->buildQuery($request, $query, 'like');

            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse([], 'No likes found', 200);
            }

            return $this->successResponse($query, 'Likes retrieved successfully', 200);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Get all likes for a post
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request) {
        try {
            $user = $request->user();
            $validatedData = $request->validate(
                $this->validationRules,
                $this->getValidationMessages()
            );

            $typeMap = [
                'post' => Post::class,
                'comment' => Comment::class,
            ];

            $likeableType = $typeMap[$validatedData['likeable_type']];
            $likeableId = $validatedData['likeable_id'];

            $simpleType = $validatedData['likeable_type'];

            $likeable = $likeableType::findOrFail($likeableId);

            if ($likeableType === Post::class && $likeable->user_id == $user->id) {
                return $this->errorResponse('You cannot like your own post', 'CANNOT_LIKE_OWN_POST', 403);
            } else if ($likeableType === Comment::class && $likeable->user_id == $user->id) {
                return $this->errorResponse('You cannot like your own comment', 'CANNOT_LIKE_OWN_COMMENT', 403);
            }

            $existingLike = Like::where([
                'user_id' => $user->id,
                'likeable_id' => $likeableId,
                'likeable_type' => $likeableType
            ])->first();

            if ($existingLike) {
                return $this->errorResponse('You have already liked this ' . $simpleType, 'ALREADY_LIKED', 403);
            }

            // Add the like and update the likes count for the likeable entity in a transaction
            $like = DB::transaction(function () use ($user, $likeableId, $likeableType, $simpleType, $likeable) {
                $like = Like::create([
                    'user_id' => $user->id,
                    'likeable_id' => $likeableId,
                    'likeable_type' => $likeableType,
                    'type' => $simpleType,
                ]);

                $this->updateLikesCount($likeable, 'increment');

                return $like;
            });

            return $this->successResponse($like, 'Like added successfully', 201);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Entity not found', 'NOT_FOUND', 404);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Remove a like
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy(Request $request) {
        try {
            $user = $request->user();
            $validatedData = $request->validate(
                $this->validationRules,
                $this->getValidationMessages()
            );

            $typeMap = [
                'post' => Post::class,
                'comment' => Comment::class,
            ];

            $likeableType = $typeMap[$validatedData['likeable_type']];
            $likeableId = $validatedData['likeable_id'];

            $like = Like::where([
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
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Entity not found', 'NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Get the liked posts for a user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getLikedPosts(Request $request) {
        try {
            $user = $request->user();

            $likedPostIds = $user->likes()
                ->where('likeable_type', Post::class)
                ->pluck('likeable_id');

            $query = Post::whereIn('id', $likedPostIds);

            $query = $this->loadRelation($request, $query, 'user', 'user_id', ['id', 'display_name']);

            $query = $this->buildQuery($request, $query, 'post');

            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse([], 'No liked posts found', 200);
            }

            return $this->successResponse($query, 'Liked posts retrieved successfully', 200);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Get the liked comments for a user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getLikedComments(Request $request) {
        try {
            $user = $request->user();

            $likedCommentIds = $user->likes()
                ->where('likeable_type', Comment::class)
                ->pluck('likeable_id');

            $query = Comment::whereIn('id', $likedCommentIds);

            $query = $this->loadRelation($request, $query, 'user', 'user_id', ['id', 'display_name']);

            $query = $this->buildQuery($request, $query, 'comment');

            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse([], 'No liked comments found', 200);
            }

            return $this->successResponse($query, 'Liked comments retrieved successfully', 200);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
