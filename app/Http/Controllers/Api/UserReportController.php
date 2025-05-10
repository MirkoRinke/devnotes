<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use App\Models\UserReport;
use App\Models\Post;
use App\Models\Comment;
use App\Models\CriticalTerm;
use App\Models\UserProfile;

use App\Traits\ApiResponses; // example return $this->successResponse($posts, 'Posts retrieved successfully', 200);
use App\Traits\QueryBuilder; // example $this->buildQuery($request, $query, $methods);
use App\Traits\CacheHelper; // example $this->cacheData($cacheKey, function () use ($query) { return $query->get(); });
use App\Traits\RelationLoader;
use App\Traits\ApiInclude;
use App\Traits\FieldManager;

use App\Services\SnapshotService;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;

class UserReportController extends Controller {

    /**
     *  The traits used in the controller
     */
    use ApiResponses, QueryBuilder, CacheHelper, AuthorizesRequests, RelationLoader, ApiInclude, FieldManager;

    /**
     * The services used in the controller
     */
    protected $snapshotService;

    /**
     * Constructor to initialize the services
     */
    public function __construct(SnapshotService $snapshotService) {
        $this->snapshotService = $snapshotService;
    }


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
     * @param string $reason The report reason to check
     * @return int The severity level
     */
    private function checkCriticalTerms(Request $request, $reason) {
        // Default severity if no critical terms are found
        $defaultSeverity = 1;

        if (empty($reason)) {
            return $defaultSeverity;
        }

        // Generate a cache key for the critical terms
        $cacheKey = $this->generateSimpleCacheKey('critical_terms');

        // Get all critical terms from the database
        // Cache the critical terms for 1 hour
        $criticalTerms = $this->cacheData($cacheKey, 3600, function () {
            return CriticalTerm::all();
        });

        // Check if any critical term is found in the reason
        foreach ($criticalTerms as $name) {
            if (stripos($reason, $name->name) !== false) {
                return $name->severity;
            }
        }

        return $defaultSeverity;
    }


    /**
     * Setup the query for user reports
     */
    protected function setupReportQuery(Request $request, $query) {
        $this->modifyRequestSelect($request, ['id', 'user_id', 'reportable_type', 'reportable_id', 'type']);

        $query = $this->loadUserRelation($request, $query);

        $query = $this->buildQuery($request, $query, 'user_reports');

        $query = $this->loadPolymorphicReportablesRelation($request, $query);

        return $query;
    }


    /**
     * Load the user relation
     * 
     * @param Request $request
     * @param mixed $query Builder|LengthAwarePaginator|Collection
     * @return mixed Builder|LengthAwarePaginator|Collection
     */
    private function loadUserRelation(Request $request, $query): mixed {
        if ($request->has('include') && in_array('user', explode(',', $request->input('include')))) {
            $query = $this->loadRelations($request, $query, [
                ['relation' => 'user', 'foreignKey' => 'user_id', 'columns' => $this->getRelationFieldsFromRequest($request, 'user', [], ['id', 'display_name', 'role', 'created_at', 'updated_at', 'is_banned', 'was_ever_banned', 'moderation_info'])],
            ]);
        }
        return $query;
    }


    /**
     * Load the polymorphic reportables relation
     * 
     * @param Request $request
     * @param mixed $query Builder|LengthAwarePaginator|Collection
     * @return mixed Builder|LengthAwarePaginator|Collection|JsonResponse
     */
    private function loadPolymorphicReportablesRelation(Request $request, $query): mixed {
        if ($query instanceof JsonResponse) {
            return $query;
        }

        if ($request->has('include') && in_array('reportable', explode(',', $request->input('include')))) {
            $reportsByType = $query->groupBy('reportable_type');

            $allowedFields = [
                Post::class => $this->getRelationFieldsFromRequest($request, 'reportable_post', [], ['*']),
                Comment::class => $this->getRelationFieldsFromRequest($request, 'reportable_comment', [], ['*']),
                UserProfile::class => $this->getRelationFieldsFromRequest($request, 'reportable_user_profile', [], ['*']),
            ];

            foreach ($reportsByType as $type => $reportsOfType) {
                if (!array_key_exists($type, $allowedFields)) {
                    continue;
                }

                $ids = $reportsOfType->pluck('reportable_id')->toArray();
                if (empty($ids)) {
                    continue;
                }

                try {
                    $fieldsToSelect = $allowedFields[$type] ?? ['id'];

                    // Load the related entities based on the type (Post, Comment, UserProfile)
                    $relatedEntities = app($type)->whereIn('id', $ids)->select($fieldsToSelect)->get()->keyBy('id');

                    foreach ($reportsOfType as $report) {
                        if (isset($relatedEntities[$report->reportable_id])) {
                            $report->setRelation('reportable', $relatedEntities[$report->reportable_id]);

                            $modelName = ucfirst($report->type);

                            // Manage the visibility of fields for the reportable entity
                            $report->reportable = $this->{"manage{$modelName}sFieldVisibility"}($request, $report->reportable);
                        }
                    }
                } catch (Exception $e) {
                    return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
                }
            }
        }
        return $query;
    }


    /**
     * Get all reports (for admin panel)
     */
    public function index(Request $request) {
        try {
            $this->authorize('viewAny', UserReport::class);

            $query = UserReport::query();

            $originalSelectFields = $this->getSelectFields($request);

            $query = $this->setupReportQuery($request, $query);

            if ($query instanceof JsonResponse) {
                return $query;
            }

            if ($query->isEmpty()) {
                return $this->successResponse([], 'No reports exist in the database', 200);
            }

            $query = $this->manageUsersFieldVisibility($request, $query);

            $query = $this->checkForIncludedRelations($request, $query);

            $query = $this->controlVisibleFields($request, $originalSelectFields, $query);

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
                $this->getValidationMessages('UserReport')
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

            $report = DB::transaction(function () use ($request, $user, $reportableId, $reportableType, $simpleType, $reportable, $validatedData) {

                $reason = $validatedData['reason'] ?? null;

                $value = 1;

                if ($user->role === 'admin' || $user->role === 'moderator') {
                    $value = 5;
                } else if ($reason) {
                    $value = $this->checkCriticalTerms($request, $reason);
                }

                // Create a snapshot of the reportable entity
                $reportableSnapshot = $this->snapshotService->createSnapshot($reportable, $reportableType);

                $report = UserReport::create([
                    'user_id' => $user->id,
                    'reportable_id' => $reportableId,
                    'reportable_type' => $reportableType,
                    'type' => $simpleType,
                    'reason' => $validatedData['reason'] ?? null,
                    'reportable_snapshot' => $reportableSnapshot,
                    'impact_value' => $value
                ]);

                $this->updateReportsCount($reportable, 'increment', $value);

                return $report;
            });

            return $this->successResponse($report, 'Report submitted successfully', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Entity not found', 'NOT_FOUND', 404);
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
                $this->getValidationMessages('UserReport')
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
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Report not found', 'NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
