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
use App\Traits\SelectableAttributes; // example $this->selectAttributes($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiPagination; // example $this->getPerPage($request, $query, 10);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);

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
    use AuthorizesRequests, ApiResponses, ApiSorting, ApiFiltering, SelectableAttributes, ApiPagination, QueryBuilder;

    /**
     * The validation rule for the like entity
     */
    private $validationRules = [
        'likeable_type' => 'required|in:post,comment',
        'likeable_id' => 'required|integer',
    ];

    /**
     * The methods array contains the methods that are used in the buildQuery method
     */
    private $methods = [
        'sort' => ['id', 'user_id', 'likeable_id', 'likeable_type', 'type', 'created_at', 'updated_at'],
        'filter' => ['user_id', 'likeable_id', 'likeable_type', 'type', 'created_at', 'updated_at'],
        'select' => ['id', 'user_id', 'likeable_id', 'likeable_type', 'type', 'created_at', 'updated_at'],
        'getPerPage' => 10
    ];


    private function updateLikesCount($likeable, $increment = true) {
        $method = $increment ? 'increment' : 'decrement';
        $likeable->$method('likes_count');
    }


    /**
     * Get all likes
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllLikes(Request $request) {
        try {
            $query = Like::query();

            if ($request->has('include')) {
                $includes = explode(',', $request->input('include'));
                $allowedIncludes = ['user', 'likeable'];
                $validIncludes = array_intersect($allowedIncludes, $includes);

                if (!empty($validIncludes)) {
                    $query->with($validIncludes);
                }
            }

            $query = $this->buildQuery($request, $query, $this->methods);

            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse([], 'No likes found', 200);
            }

            return $this->successResponse($query, 'Likes retrieved successfully', 200);
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
    public function addLike(Request $request) {
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

                $this->updateLikesCount($likeable, true);

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
    public function removeLike(Request $request) {
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
                $this->updateLikesCount($likeable, false);
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
}
