<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\UserReport;
use App\Models\Post;
use App\Models\Comment;
use App\Models\UserProfile;
use App\Traits\ApiResponses; // example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\ApiSorting;  // example $query = $this->sort(request(), $query, ['id', 'title', 'language', 'category', 'status']);
use App\Traits\ApiFiltering; // example $query = $this->filter(request(), $query, ['title', 'language', 'category', 'status']);
use App\Traits\ApiSelectable; // example $this->selectAttributes($request, $query, [ 'id','name', 'email']);
use App\Traits\ApiPagination; // example $this->getPerPage($request, $query, 10);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserReportController extends Controller {

    /**
     *  The traits used in the controller
     */
    use AuthorizesRequests, ApiResponses, ApiSorting, ApiFiltering, ApiSelectable, ApiPagination, QueryBuilder;

    /**
     * The validation rule for the user report data
     */
    private $validationRules = [
        'user_id' => 'integer',
        'reportable_type' => 'required|in:post,userProfile,comment',
        'reportable_id' => 'required|integer',
        'reason' => 'nullable|string|max:500'
    ];

    /**
     * Update the reports_count for a reportable entity
     *
     * @param mixed $reportable The reportable entity (Post, User, Comment)
     * @param string $reportableType The fully qualified class name of the reportable
     * @param bool $increment Whether to increment or decrement the counter
     * @return void
     */
    private function updateReportsCount($reportable, $method = 'increment', $value = 1) {
        $reportable->$method('reports_count', $value);
    }

    /**
     * Check for critical terms in the reason
     * 
     * TODO: Get the critical terms from the database
     */
    private function checkCriticalTerms($reason) {
        $criticalTerms = [
            // EN
            ...['scam', 'malware', 'phishing', 'harmful', 'porn', 'nude', 'nudity', 'abuse', 'sexual'],
            // DE
            ...['betrug', 'schadsoftware', 'phishing', 'schädlich', 'pornografie', 'nackt', 'nacktheit', 'missbrauch', 'sexuell'],
        ];

        foreach ($criticalTerms as $term) {
            if (stripos($reason, $term) !== false) {
                return 4;
            }
        }
        return 1;
    }


    /**
     * Get all reports (for admin panel)
     */
    public function index(Request $request) {
        try {

            $this->authorize('viewAny', UserReport::class);

            $query = UserReport::query();

            /**
             *  Include the user and reportable entity in the response
             */
            if ($request->has('include')) {
                $includes = explode(',', $request->input('include'));
                $allowedIncludes = ['user', 'reportable'];
                $validIncludes = array_intersect($allowedIncludes, $includes);

                if (!empty($validIncludes)) {
                    $query->with($validIncludes);
                }
            }

            $query = $this->buildQuery($request, $query, 'user_reports');

            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse([], 'No reports exist in the database', 200);
            }

            return $this->successResponse($query, 'Reports retrieved successfully', 200);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Add a report for any reportable entity (Post, User, Comment)
     */
    public function store(Request $request) {
        try {
            $user = $request->user();
            $validatedData = $request->validate(
                $this->validationRules,
                $this->getValidationMessages()
            );

            $typeMap = [
                'userProfile' => UserProfile::class,
                'post' => Post::class,
                'comment' => Comment::class,
            ];

            $reportableType = $typeMap[$validatedData['reportable_type']];
            $reportableId = $validatedData['reportable_id'];

            $simpleType = $validatedData['reportable_type'];

            $reportable = $reportableType::findOrFail($reportableId);

            if ($reportableType === UserProfile::class && $reportable->user_id == $user->id) {
                return $this->errorResponse('You cannot report yourself', 'CANNOT_REPORT_SELF', 403);
            } else if ($reportableType === Post::class && $reportable->user_id == $user->id) {
                return $this->errorResponse('You cannot report your own post', 'CANNOT_REPORT_OWN_POST', 403);
            } else if ($reportableType === Comment::class && $reportable->user_id == $user->id) {
                return $this->errorResponse('You cannot report your own comment', 'CANNOT_REPORT_OWN_COMMENT', 403);
            }

            $existingReport = UserReport::where([
                'user_id' => $user->id,
                'reportable_id' => $reportableId,
                'reportable_type' => $reportableType
            ])->first();

            if ($existingReport) {
                return $this->errorResponse('You have already reported this ' . $simpleType, 'ALREADY_REPORTED',  409);
            }

            $report = DB::transaction(function () use ($user, $reportableId, $reportableType, $simpleType, $reportable, $validatedData) {

                $reason = $validatedData['reason'] ?? null;

                if ($reason && $user->role === 'user') {
                    $value = $this->checkCriticalTerms($reason);
                } else if ($user->role === 'admin' || $user->role === 'moderator') {
                    $value = 5;
                }

                $report = UserReport::create([
                    'user_id' => $user->id,
                    'reportable_id' => $reportableId,
                    'reportable_type' => $reportableType,
                    'type' => $simpleType,
                    'reason' => $validatedData['reason'] ?? null,
                    'impact_value' => $value
                ]);

                $this->updateReportsCount($reportable, 'increment', $value);

                return $report;
            });

            return $this->successResponse($report, 'Report submitted successfully', 201);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Entity not found', 'NOT_FOUND', 404);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Remove a report
     */
    public function destroy(Request $request) {
        try {
            $user = $request->user();
            $validatedData = $request->validate(
                $this->validationRules,
                $this->getValidationMessages()
            );

            $typeMap = [
                'userProfile' => UserProfile::class,
                'post' => Post::class,
                'comment' => Comment::class,
            ];

            if ($user->role === 'admin') {
                $userId = $validatedData['user_id'] ?? null;
            } else {
                $userId = null;
            }

            $reportableType = $typeMap[$validatedData['reportable_type']];
            $reportableId = $validatedData['reportable_id'];

            $report = UserReport::where([
                'user_id' => $userId ?? $user->id,
                'reportable_id' => $reportableId,
                'reportable_type' => $reportableType
            ])->firstOrFail();

            $this->authorize('delete', $report);

            $reportable = $report->reportable;

            $value = $report->impact_value;


            DB::transaction(function () use ($report, $reportable, $value) {
                $this->updateReportsCount($reportable, 'decrement', $value);
                $report->delete();
            });

            return $this->successResponse(null, 'Report removed successfully', 200);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Report not found', 'NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
