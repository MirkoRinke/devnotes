<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

use App\Models\UserReport;
use App\Models\Post;
use App\Models\Comment;
use App\Models\User;
use App\Models\UserProfile;

use App\Traits\ApiResponses;
use App\Traits\QueryBuilder;
use App\Traits\CacheHelper;
use App\Traits\RelationLoader;
use App\Traits\ApiInclude;
use App\Traits\FieldManager;
use App\Traits\ReportHelper;

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
    use ApiResponses, QueryBuilder, CacheHelper, AuthorizesRequests, RelationLoader, ApiInclude, FieldManager, ReportHelper;

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
     * 
     * @return array
     * 
     * @example | $this->getValidationRules()
     */
    public function getValidationRules(): array {
        $validationRules = [
            'reportable_type' => 'required|in:post,userProfile,comment',
            'reportable_id' => 'required|integer',
            'reason' => 'nullable|string|max:500'
        ];
        return $validationRules;
    }

    /**
     * Update the reports_count for a reportable entity
     *
     * @param mixed $reportable The reportable entity (Post, UserProfile, Comment)
     * @param string $method The method to call on the reportable entity (increment or decrement)
     * @param int $value The value to increment or decrement by (default is 1)
     * @return void
     * 
     * @example | $this->updateReportsCount($reportable, 'increment', 1);
     */
    private function updateReportsCount($reportable, string $method, int $value = 1): void {
        $reportable->$method('reports_count', $value);
    }

    /**
     * Setup the query for user reports
     * 
     * @param Request $request
     * @param mixed $query 
     * @return mixed
     * 
     * @example | $this->setupReportQuery($request, $query);
     */
    protected function setupReportQuery(Request $request, $query) {
        $this->modifyRequestSelect($request, ['id', 'user_id', 'reportable_type', 'reportable_id', 'type']);

        $query = $this->loadUserRelation($request, $query, 'user_id');

        $query = $this->buildQuery($request, $query, 'user_reports');

        $query = $this->loadReportableRelation($request, $query);

        return $query;
    }

    /**
     * Load the reportable polymorphic relation
     * 
     * @param Request $request
     * @param mixed $query Builder|LengthAwarePaginator|Collection
     * @return mixed Builder|LengthAwarePaginator|Collection
     * 
     * @example | $this->loadReportableRelation($request, $query);
     */
    private function loadReportableRelation(Request $request, $query): mixed {
        if ($request->has('include') && in_array('reportable', explode(',', $request->input('include')))) {
            $query = $this->loadPolymorphicRelations(
                $request,
                $query,
                'reportable',
                [
                    Post::class => $this->getRelationFieldsFromRequest($request, 'reportable_post', [], ['*']),
                    Comment::class => $this->getRelationFieldsFromRequest($request, 'reportable_comment', [], ['*']),
                    UserProfile::class => $this->getRelationFieldsFromRequest($request, 'reportable_profile', [], ['*']),
                ]
            );
        }
        return $query;
    }


    /**
     * Get All Reports
     * 
     * Endpoint: GET /reports
     *
     * Retrieves a list of all reports in the system with support for filtering, sorting,
     * and relation inclusion. Only administrators can access this endpoint.
     *
     * @group Report Management
     *
     * @queryParam select string Select specific fields. Example: select=id,user_id,reportable_id
     * @queryParam sort string Sort by field (prefix with - for descending order). Example: sort=-created_at
     * @queryParam filter[type] string Filter by reportable type. Example: filter[type]=post
     * 
     * @queryParam startsWith[field] string Filter where field starts with given string. Example: startsWith[created_at]=2024-01
     * @queryParam endsWith[field] string Filter where field ends with given string. Example: endsWith[reason]=content
     * 
     * @queryParam include string Comma-separated relations to include. Example: include=user,reportable
     * @queryParam user_fields string When including user relation, specify fields to return. 
     *                                Available fields: id, display_name, role, created_at, updated_at , is_banned, was_ever_banned, moderation_info
     *                                Example: user_fields=id,display_name
     * @queryParam reportable_post_fields string When including reportable relation (for posts), specify fields to return.
     *                                Example: reportable_post_fields=id,title,description
     * @queryParam reportable_comment_fields string When including reportable relation (for comments), specify fields to return.
     *                                Example: reportable_comment_fields=id,content
     * @queryParam reportable_profile_fields string When including reportable relation (for user profiles), specify fields to return.
     *                                Example: reportable_profile_fields=id,display_name,public_email
     * 
     * @queryParam page integer Page number for pagination. Example: page=1
     * @queryParam per_page integer Items per page. Example: per_page=15 (default: 10)
     *
     * Example URL: /reports
     * 
     * @response status=200 scenario="Success" {
     *   "success": true,
     *   "message": "Reports retrieved successfully",
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 2,
     *       "reportable_type": "App\\Models\\Post",
     *       "reportable_id": 5,
     *       "type": "post",
     *       "reason": "This post contains inappropriate content",
     *       "impact_value": 2,
     *       "created_at": "2024-01-15T14:30:00Z",
     *       "updated_at": "2024-01-15T14:30:00Z"
     *     },
     *     {
     *       "id": 2,
     *       "user_id": 3,
     *       "reportable_type": "App\\Models\\Comment",
     *       "reportable_id": 12,
     *       "type": "comment",
     *       "reason": "This comment contains offensive language",
     *       "impact_value": 3,
     *       "created_at": "2024-01-16T09:45:22Z",
     *       "updated_at": "2024-01-16T09:45:22Z"
     *     }
     *   ]
     * }
     * 
     * Example URL: /reports/?include=user&user_fields=id,display_name
     * 
     * @response status=200 scenario="With user relation" {
     *   "success": true,
     *   "message": "Reports retrieved successfully",
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 2,
     *       "reportable_type": "App\\Models\\Post",
     *       "reportable_id": 5,
     *       "type": "post",
     *       "reason": "This post contains inappropriate content",
     *       "impact_value": 2,
     *       "created_at": "2024-01-15T14:30:00Z",
     *       "updated_at": "2024-01-15T14:30:00Z",
     *       "user": {
     *         "id": 2,
     *         "display_name": "JohnDoe"
     *       }
     *     }
     *   ]
     * }
     * 
     * Example URL: /reports/?include=reportable&reportable_post_fields=id,title,description
     * 
     * @response status=200 scenario="With reportable" {
     *   "success": true,
     *   "message": "Reports retrieved successfully",
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 2,
     *       "reportable_type": "App\\Models\\Post",
     *       "reportable_id": 5,
     *       "type": "post",
     *       "reason": "This post contains inappropriate content",
     *       "impact_value": 2,
     *       "created_at": "2024-01-15T14:30:00Z",
     *       "updated_at": "2024-01-15T14:30:00Z",
     *       "reportable": {
     *         "id": 5,
     *         "title": "Node.js: RESTful API",
     *         "description": "Learn how to build a RESTful API using Node.js and Express."
     *       }
     *     }
     *   ]
     * }
     * 
     * @response status=200 scenario="No reports found" {
     *   "status": "success",
     *   "message": "No reports exist in the database",
     *   "code": 200,
     *   "count": 0,
     *   "data": []
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
     * Note: This endpoint requires admin privileges as it accesses all reports in the system.
     * 
     * @authenticated
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
     * Create a Report
     * 
     * Endpoint: POST /reports
     *
     * Creates a new report for a post, comment, or user profile. Users cannot report their own content
     * and cannot report the same content multiple times.
     *
     * @group Report Management
     *
     * @bodyParam reportable_type string required The type of entity to report ('post', 'comment', or 'userProfile'). Example: comment
     * @bodyParam reportable_id integer required The ID of the entity to report. Example: 9
     * @bodyParam reason string optional The reason for reporting this content. Example: "This comment contains inappropriate content"
     *
     * @bodyContent {
     *   "reportable_type": "comment",                                  || required, string, in:post,comment,userProfile
     *   "reportable_id": 9,                                            || required, integer
     *   "reason": "This comment contains inappropriate content"        || optional, string, max:500
     * }
     *
     * @response status=201 scenario="Success" {
     *   "status": "success",
     *   "message": "Report submitted successfully",
     *   "code": 201,
     *   "count": 1,
     *   "data": {
     *     "user_id": 1,
     *     "reportable_id": 9,
     *     "reportable_type": "App\\Models\\Comment",
     *     "type": "comment",
     *     "reason": "This comment contains inappropriate content",
     *     "reportable_snapshot": {
     *       "user_id": 7,
     *       "post_id": 6,
     *       "parent_id": null,
     *       "content": "Docker has revolutionized my development environment!",
     *       "parent_content": null,
     *       "user_data": {
     *         "name": "Max Mustermann7",
     *         "email": "max@example7.com",
     *         "role": "user"
     *       }
     *     },
     *     "impact_value": 5,
     *     "updated_at": "2025-05-11T20:46:00.000000Z",
     *     "created_at": "2025-05-11T20:46:00.000000Z",
     *     "id": 11
     *   }
     * }
     *
     * @response status=403 scenario="Cannot report own content" {
     *   "status": "error",
     *   "message": "You cannot report your own comment",
     *   "code": 403,
     *   "errors": "CANNOT_REPORT_OWN_COMMENT"
     * }
     *
     * @response status=409 scenario="Already reported" {
     *   "status": "error",
     *   "message": "You have already reported this comment",
     *   "code": 409,
     *   "errors": "ALREADY_REPORTED"
     * }
     *
     * @response status=404 scenario="Entity not found" {
     *   "status": "error",
     *   "message": "Entity not found",
     *   "code": 404,
     *   "errors": "NOT_FOUND"
     * }
     *
     * @response status=422 scenario="Validation error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "reportable_type": ["REPORTABLE_TYPE_INVALID_OPTION"],
     *     "reportable_id": ["REPORTABLE_ID_MUST_BE_INTEGER"]
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
     * Admin/moderator reports have higher impact values.
     *
     */
    public function store(Request $request) {
        try {
            $fallbackUserId = 4; // Fallback user ID ( Guest Report )

            $user = $this->getAuthenticatedUser($request) ?? User::query()->where('id', $fallbackUserId)->get()->first();

            $validatedData = $request->validate(
                $this->getValidationRules(),
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

            $validationResult = $this->reportValidationCheck($user, $reportableType, $reportable, $reportableId, $simpleType);
            if ($validationResult !== null) {
                return $validationResult;
            }

            $report = DB::transaction(function () use ($user, $reportableId, $reportableType, $simpleType, $reportable, $validatedData, $fallbackUserId) {
                $reason = $validatedData['reason'] ?? null;
                $value = 1;

                if ($user->role === 'admin' || $user->role === 'moderator') {
                    $value = 5;
                } else if ($user->id === $fallbackUserId) {
                    $value = 1; // Guest Report value
                } else if ($reason) {
                    $value = $this->checkCriticalTerms($reason);
                }

                // Create a snapshot of the reportable entity
                $reportableSnapshot = $this->snapshotService->createSnapshot($reportable, $reportableType);

                // Create the report
                $report = new UserReport();

                $report->user_id = $user->id;
                $report->reportable_id = $reportableId;
                $report->reportable_type = $reportableType;
                $report->type = $simpleType;
                $report->reason = $reason;
                $report->reportable_snapshot = $reportableSnapshot;
                $report->impact_value = $value;

                $report->save();

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
     * Remove a Report
     * 
     * Endpoint: DELETE /reports
     *
     * Removes a report from a post, comment, or user profile. Regular users can only remove their own reports,
     * while administrators can remove any report by specifying a user_id.
     *
     * @group Report Management
     *
     * @bodyParam reportable_type string required The type of entity ('post', 'comment', or 'userProfile'). Example: comment
     * @bodyParam reportable_id integer required The ID of the entity. Example: 9
     * @bodyParam user_id integer optional For admins only: ID of the user whose report should be removed. 
     *                                    If not provided, the authenticated user's report will be removed. Example: 3
     *
     * @bodyContent {
     *   "reportable_type": "comment",                                  || required, string, in:post,comment,userProfile
     *   "reportable_id": 9,                                            || required, integer
     *   "user_id": 3                                                   || optional, integer, admin only
     * }
     *
     * @response status=200 scenario="Success" {
     *   "status": "success",
     *   "message": "Comment Report removed successfully",
     *   "code": 200,
     *   "count": 0,
     *   "data": null
     * }
     *
     * @response status=404 scenario="Report not found" {
     *   "status": "error",
     *   "message": "Report not found",
     *   "code": 404,
     *   "errors": "NOT_FOUND"
     * }
     *
     * @response status=403 scenario="Non-Admin trying to delete another user's report" {
     *   "status": "error",
     *   "message": "Unauthorized",
     *   "code": 403,
     *   "errors": "UNAUTHORIZED"
     * }
     *
     * @response status=422 scenario="Validation error" {
     *   "status": "error",
     *   "message": "Validation failed",
     *   "code": 422,
     *   "errors": {
     *     "reportable_type": ["REPORTABLE_TYPE_INVALID_OPTION"],
     *     "reportable_id": ["REPORTABLE_ID_FIELD_REQUIRED"]
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
     * Note: Regular users can only remove their own reports. Administrators can remove any report
     * by specifying the user_id in the request.
     *
     * @authenticated
     */
    public function destroy(Request $request) {
        try {
            $user = $request->user();

            $validationRules = $this->getValidationRules();

            $isAdmin = $user->role === 'admin';

            if ($isAdmin) {
                $validationRules['user_id'] = 'nullable|integer';
            }

            $validatedData = $request->validate(
                $validationRules,
                $this->getValidationMessages('UserReport')
            );


            if ($isAdmin && isset($validatedData['user_id'])) {
                $user_id = $validatedData['user_id'];
            }

            $typeMap = [
                'userProfile' => UserProfile::class,
                'post' => Post::class,
                'comment' => Comment::class,
            ];

            $reportableType = $typeMap[$validatedData['reportable_type']];
            $reportableId = $validatedData['reportable_id'];

            $report = UserReport::where([
                'user_id' => $user_id ?? $user->id,
                'reportable_id' => $reportableId,
                'reportable_type' => $reportableType
            ])->firstOrFail();

            $this->authorize('delete', $report);

            // The entity being reported
            $reportable = $report->reportable;

            $destroyedType = ucfirst($validatedData['reportable_type']);

            // The impact value of the original report
            $value = $report->impact_value;

            DB::transaction(function () use ($report, $reportable, $value) {
                $this->updateReportsCount($reportable, 'decrement', $value);
                $report->delete();
            });

            return $this->successResponse(null, "$destroyedType Report removed successfully", 200);
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
