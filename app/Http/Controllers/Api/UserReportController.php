<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\UserReport;
use App\Models\Post;

use App\Traits\ApiResponses; // example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\ApiSorting;  // example $query = $this->sort(request(), $query, ['id', 'title', 'language', 'category', 'status']);
use App\Traits\ApiFiltering; // example $query = $this->filter(request(), $query, ['title', 'language', 'category', 'status']);
use App\Traits\SelectableAttributes; // example $this->selectAttributes($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiPagination; // example $this->getPerPage($request, $query, 10);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserReportController extends Controller {

    /**
     *  The traits used in the controller
     */
    use AuthorizesRequests, ApiResponses, ApiSorting, ApiFiltering, SelectableAttributes, ApiPagination, QueryBuilder;

    /**
     * The methods array contains the methods that are used in the buildQuery method
     */
    private $methods = [
        'sort' => ['id', 'user_id', 'title', 'language', 'category', 'tags', 'status', 'favorite_count', 'created_at', 'updated_at'],
        'filter' => ['title', 'user_id', 'language', 'category', 'tags', 'status', 'created_at', 'updated_at'],
        'select' => ['id', 'user_id', 'title', 'code', 'description', 'resources', 'language', 'category', 'tags', 'status', 'favorite_count', 'reports_count', 'created_at', 'updated_at'],
        'getPerPage' => 10
    ];

    /**
     * Get all reports
     */
    public function getReports(Request $request) {
        $user = $request->user();

        $query = UserReport::where('user_id', $user->id)->with('post');

        $query = $this->buildQuery($request, $query, $this->methods);

        if ($query instanceof JsonResponse) {
            return $query;
        }

        if ($query->isEmpty()) {
            return $this->successResponse($query, 'No reports found', 200);
        }

        return $this->successResponse($query, 'Reports retrieved successfully', 200);
    }

    /**
     * Add a post to reports
     */
    public function addReport(Request $request, $postId) {
        try {
            $user = $request->user();
            $post = Post::findOrFail($postId);

            $this->authorize('create', [UserReport::class, $post]);

            $exists = UserReport::where('user_id', $user->id)->where('post_id', $post->id)->exists();

            if (!$exists) {
                $report = UserReport::create(['user_id' => $user->id, 'post_id' => $post->id]);

                $post->increment('reports_count');

                return $this->successResponse($report, 'Post successfully added to reports', 201);
            } else {
                $report = UserReport::where('user_id', $user->id)->where('post_id', $post->id)->first();

                return $this->successResponse($report, 'Post already in reports', 200);
            }
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Post not found', 'POST_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('You cannot report your own post', 'CANNOT_REPORT_OWN_POST', 403);
        }
    }

    /**
     * Remove a post from reports
     */
    public function removeReport(Request $request, $postId) {
        try {
            $user = $request->user();
            $post = Post::findOrFail($postId);

            $report = UserReport::where('user_id', $user->id)->where('post_id', $postId)->first();

            if (!$report) {
                return $this->errorResponse('Post is not in reports', 'POST_NOT_IN_REPORTS', 404);
            }

            $this->authorize('delete', $report);

            $post->decrement('reports_count');
            $report->delete();

            return $this->successResponse(null, 'Post successfully removed from reports', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Post not found', 'POST_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('You cannot remove this post from reports', 'CANNOT_REMOVE_POST_FROM_REPORTS', 403);
        }
    }
}
