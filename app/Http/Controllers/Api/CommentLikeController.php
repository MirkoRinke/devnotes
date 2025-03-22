<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\CommentLike;
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

class CommentLikeController extends Controller {

    /**
     *  The traits used in the controller
     */
    use AuthorizesRequests, ApiResponses, ApiSorting, ApiFiltering, SelectableAttributes, ApiPagination, QueryBuilder;

    /**
     * The methods array contains the methods that are used in the buildQuery method
     */
    private $methodsLikes = [
        'sort' =>  ['id', 'user_id', 'comment_id', 'created_at', 'updated_at'],
        'filter' => ['id', 'user_id', 'comment_id', 'created_at', 'updated_at'],
        'select' =>  ['id', 'user_id', 'comment_id', 'created_at', 'updated_at'],
        'getPerPage' => 10
    ];

    private $methodsComment = [
        'sort' => ['id', 'post_id', 'user_id', 'is_deleted', 'is_edited', 'edited_at', 'likes_count', 'reports_count', 'created_at', 'updated_at'],
        'filter' => ['post_id', 'user_id', 'parent_id', 'is_deleted', 'is_edited', 'edited_at', 'likes_count', 'reports_count', 'created_at', 'updated_at'],
        'select' => ['post_id', 'user_id', 'content', 'parent_id', 'is_deleted', 'is_edited', 'edited_at', 'likes_count', 'reports_count', 'created_at', 'updated_at'],
        'getPerPage' => 10
    ];


    /**
     * Get all likes
     */

    public function getLikes(Request $request): JsonResponse {
        $user = $request->user();

        $query = CommentLike::where('user_id', $user->id);

        /**
         *  Include the comment entity in the response
         */
        if ($request->has('include')) {
            $includes = explode(',', $request->include);
            $allowedIncludes = ['comment'];
            $validIncludes = array_intersect($includes, $allowedIncludes);

            if (!empty($validIncludes)) {
                $query->with($validIncludes);
            }
        }

        $query = $this->buildQuery($request, $query, $this->methodsLikes);

        if ($query instanceof JsonResponse) {
            return $query;
        }

        if ($query->isEmpty()) {
            return $this->successResponse($query, 'No likes found', 200);
        }

        return $this->successResponse($query, 'Likes retrieved successfully', 200);
    }


    /**
     * Add a comment to likes
     */
    public function addLike(Request $request, $commentId): JsonResponse {
        try {
            $user = $request->user();
            $comment = Comment::findOrFail($commentId);

            $exists = CommentLike::where('user_id', $user->id)->where('comment_id', $comment->id)->exists();

            if (!$exists) {
                $like = CommentLike::create(['user_id' => $user->id, 'comment_id' => $comment->id]);

                $comment->increment('likes_count');

                return $this->successResponse($like, 'Comment added to likes', 201);
            } else {
                $like = CommentLike::where('user_id', $user->id)->where('comment_id', $comment->id)->first();

                return $this->successResponse($like, 'Comment already in likes', 200);
            }
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Comment not found', 'COMMENT_NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Remove a comment from likes
     */
    public function removeLike(Request $request, $commentId): JsonResponse {
        try {
            $user = $request->user();
            $comment = Comment::findOrFail($commentId);

            $like = CommentLike::where('user_id', $user->id)->where('comment_id', $commentId)->first();

            if (!$like) {
                return $this->errorResponse('Comment is not in likes', 'COMMENT_NOT_IN_LIKES', 404);
            }

            $this->authorize('delete', $like);

            $comment->decrement('likes_count');
            $like->delete();

            return $this->successResponse(null, 'Comment successfully removed from likes', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Comment not found', 'COMMENT_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }


    /**
     * Display a listing of comments that are liked by the authenticated user.
     * Supports the same query parameters as the post index endpoint.
     */
    public function getLikedComments(Request $request): JsonResponse {
        try {
            $userId = $request->user()->id;

            // Get comments that have been liked by this user
            $query = Comment::query()->whereHas('likes', function ($subQuery) use ($userId) {
                $subQuery->where('user_id', $userId);
            });

            $query = $this->buildQuery($request, $query, $this->methodsComment);

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
