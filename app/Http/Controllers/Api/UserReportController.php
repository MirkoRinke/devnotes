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
                    Post::class => $this->getRelationFieldsFromRequest($request, 'reportable_post', ['user_id'], ['*']),
                    Comment::class => $this->getRelationFieldsFromRequest($request, 'reportable_comment', ['reports_count'], ['*']),
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
     * @queryParam select   See [ApiSelectable](#apiselectable) for field selection details.
     * @see \App\Traits\ApiSelectable::select()
     * 
     * @queryParam sort     See [ApiSorting](#apisorting) for sorting details.
     * @see \App\Traits\ApiSorting::sort()
     * 
     * @queryParam filter[type] string Filter by reportable type. Example: filter[type]=post
     * @see \App\Traits\ApiFiltering::filter()
     * 
     * @queryParam filter[user_id] integer Filter by user ID. Example: filter[user_id]=5
     * @see \App\Traits\ApiFiltering::filter()
     * 
     * @queryParam include  See [ApiInclude](#apiinclude) for relation inclusion details (e.g. user, reportable).
     * @see \App\Traits\ApiInclude::getRelationKeyFields()
     * 
     * @queryParam user_fields string See [ApiInclude](#apiinclude). When including user relation, specify fields to return. Example: user_fields=id,display_name
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest()
     * 
     * @queryParam reportable_post_fields string See [ApiInclude](#apiinclude). When including reportable relation (for posts), specify fields to return. Example: reportable_post_fields=id,title,description
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest()
     * 
     * @queryParam reportable_comment_fields string See [ApiInclude](#apiinclude). When including reportable relation (for comments), specify fields to return. Example: reportable_comment_fields=id,content
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest()
     * 
     * @queryParam reportable_profile_fields string See [ApiInclude](#apiinclude). When including reportable relation (for user profiles), specify fields to return. Example: reportable_profile_fields=id,display_name,public_email
     * @see \App\Traits\ApiInclude::getRelationFieldsFromRequest()
     * 
     * @queryParam page     Pagination, see [ApiPagination](#apipagination).
     * @see \App\Traits\ApiPagination::paginate()
     * 
     * @queryParam per_page Pagination, see [ApiPagination](#apipagination).
     * @see \App\Traits\ApiPagination::paginate()
     * 
     * @queryParam setLimit Disables pagination and limits the number of results. See [ApiLimit](#apilimit).
     * @see \App\Traits\ApiLimit::setLimit()
     *
     * Example URL: /reports
     * 
     * @response status=200 scenario="Success (no includes)" {
     *   "status": "success",
     *   "message": "Reports retrieved successfully",
     *   "code": 200,
     *   "count": 3,
     *   "data": [
     *     {
     *       "id": 4,
     *       "user_id": 1,
     *       "reportable_type": "App\\Models\\Post",
     *       "reportable_id": 123,
     *       "type": "post",
     *       "reason": "These posts contain inappropriate content",
     *       "reportable_snapshot": { ... },
     *       "impact_value": 5,
     *       "created_at": "2025-07-13T19:17:31.000000Z",
     *       "updated_at": "2025-07-13T19:17:31.000000Z"
     *     }
     *   ]
     * }
     *
     * Example URL: /reports?include=reportable,user
     *
     * @response status=200 scenario="Success (include: post, user)" {
     *   "status": "success",
     *   "message": "Reports retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 4,
     *       "user_id": 1,
     *       "reportable_type": "App\\Models\\Post",
     *       "reportable_id": 123,
     *       "type": "post",
     *       "reason": "These posts contain inappropriate content",
     *       "reportable_snapshot": { ... },
     *       "impact_value": 5,
     *       "created_at": "2025-07-13T19:17:31.000000Z",
     *       "updated_at": "2025-07-13T19:17:31.000000Z",
     *       "reportable": {
     *         "id": 123,
     *         "user_id": 311,
     *         "title": "Hello World",
     *         "code": " <?php echo 'echo \"Hello World\"; ?>",
     *         "description": "This is a test post",
     *         "images": [
     *           "https://picsum.photos/id/227/200/300",
     *           "https://picsum.photos/id/728/200/300"
     *         ],
     *         "videos": [
     *           "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
     *           "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
     *           "https://www.youtube.com/watch?v=dQw4w9WgXcQ"
     *         ],
     *         "resources": [
     *           "https://laravel.com/docs/master"
     *         ],
     *         "external_source_previews": [
     *           {
     *             "url": "https://picsum.photos/id/227/200/300",
     *             "type": "images",
     *             "domain": "picsum.photos"
     *           },
     *           {
     *             "url": "https://picsum.photos/id/728/200/300",
     *             "type": "images",
     *             "domain": "picsum.photos"
     *           },
     *           {
     *             "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
     *             "type": "videos",
     *             "domain": "www.youtube.com"
     *           },
     *           {
     *             "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
     *             "type": "videos",
     *             "domain": "www.youtube.com"
     *           },
     *           {
     *             "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
     *             "type": "videos",
     *             "domain": "www.youtube.com"
     *           },
     *           {
     *             "url": "https://laravel.com/docs/master",
     *             "type": "resources",
     *             "domain": "laravel.com"
     *           }
     *         ],
     *         "category": "Fullstack",
     *         "post_type": "Feedback",
     *         "status": "Private",
     *         "favorite_count": 5,
     *         "likes_count": 2,
     *         "reports_count": 5,
     *         "comments_count": 1,
     *         "is_updated": false,
     *         "updated_by_role": null,
     *         "last_comment_at": "2025-07-09T17:27:09.000000Z",
     *         "history": [],
     *         "moderation_info": [],
     *         "created_at": "2025-07-09T17:26:53.000000Z",
     *         "updated_at": "2025-07-13T19:17:31.000000Z"
     *       },
     *       "user": {
     *         "id": 1,
     *         "display_name": "Admin",
     *         "role": "admin",
     *         "created_at": "2025-07-09T17:26:42.000000Z",
     *         "updated_at": "2025-07-09T17:26:42.000000Z",
     *         "is_banned": null,
     *         "was_ever_banned": false,
     *         "moderation_info": []
     *       }
     *     }
     *   ]
     * }
     *
     * @response status=200 scenario="Success (include: comment, user)" {
     *   "status": "success",
     *   "message": "Reports retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 6,
     *       "user_id": 1,
     *       "reportable_type": "App\\Models\\Comment",
     *       "reportable_id": 2,
     *       "type": "comment",
     *       "reason": "This comment contains inappropriate content",
     *       "reportable_snapshot": { ... },
     *       "impact_value": 5,
     *       "created_at": "2025-07-13T19:17:37.000000Z",
     *       "updated_at": "2025-07-13T19:17:37.000000Z",
     *       "reportable": {
     *         "id": 2,
     *         "post_id": 326,
     *         "user_id": 131,
     *         "parent_id": null,
     *         "content": "This comment has been reported too many times and is no longer available",
     *         "parent_content": null,
     *         "is_deleted": false,
     *         "depth": 0,
     *         "likes_count": 4,
     *         "reports_count": 5,
     *         "is_updated": false,
     *         "updated_by_role": null,
     *         "moderation_info": [],
     *         "created_at": "2025-07-09T17:27:05.000000Z",
     *         "updated_at": "2025-07-13T19:17:37.000000Z"
     *       },
     *       "user": {
     *         "id": 1,
     *         "display_name": "Admin",
     *         "role": "admin",
     *         "created_at": "2025-07-09T17:26:42.000000Z",
     *         "updated_at": "2025-07-09T17:26:42.000000Z",
     *         "is_banned": null,
     *         "was_ever_banned": false,
     *         "moderation_info": []
     *       }
     *     }
     *   ]
     * }
     *
     * @response status=200 scenario="Success (include: userProfile, user)" {
     *   "status": "success",
     *   "message": "Reports retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": [
     *     {
     *       "id": 5,
     *       "user_id": 1,
     *       "reportable_type": "App\\Models\\UserProfile",
     *       "reportable_id": 125,
     *       "type": "userProfile",
     *       "reason": "This user profile contains inappropriate content",
     *       "reportable_snapshot": { ... },
     *       "impact_value": 5,
     *       "created_at": "2025-07-13T19:17:34.000000Z",
     *       "updated_at": "2025-07-13T19:17:34.000000Z",
     *       "reportable": {
     *         "id": 125,
     *         "user_id": 125,
     *         "display_name": "johndoe",
     *         "public_email": "johndoe@example.com",
     *         "website": "localhost:8000",
     *         "avatar_path": null,
     *         "is_public": true,
     *         "location": "Berlin, Germany",
     *         "biography": "Software developer with a passion for open source projects.",
     *         "skills": "PHP, Laravel, JavaScript",
     *         "social_links": null,
     *         "contact_channels": null,
     *         "auto_load_external_images": false,
     *         "external_images_temp_until": null,
     *         "auto_load_external_videos": false,
     *         "external_videos_temp_until": null,
     *         "auto_load_external_resources": false,
     *         "external_resources_temp_until": null,
     *         "reports_count": 5,
     *         "created_at": "2025-07-09T17:26:46.000000Z",
     *         "updated_at": "2025-07-13T19:17:34.000000Z"
     *       },
     *       "user": {
     *         "id": 1,
     *         "display_name": "Admin",
     *         "role": "admin",
     *         "created_at": "2025-07-09T17:26:42.000000Z",
     *         "updated_at": "2025-07-09T17:26:42.000000Z",
     *         "is_banned": null,
     *         "was_ever_banned": false,
     *         "moderation_info": []
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
     * Creates a new report for a post, comment, or user profile.
     * Users cannot report their own content and cannot report the same content multiple times.
     * Guests can submit reports; their report will use a fallback user and always have impact_value 1.
     * Admins and moderators receive impact_value 5.
     * For regular users, impact_value may increase if critical terms are found in the reason.
     *
     * @group Report Management
     *
     * @bodyParam reportable_type string required The type of entity to report ('post', 'comment', or 'userProfile'). Example: post
     * @bodyParam reportable_id integer required The ID of the entity to report. Example: 42
     * @bodyParam reason string optional The reason for reporting this content. Example: "This post contains spam and inappropriate language."
     *
     * @bodyContent {
     *   "reportable_type": "post",                                         || required, string, in:post,comment,userProfile
     *   "reportable_id": 42,                                               || required, integer
     *   "reason": "This post contains spam and inappropriate language."    || optional, string, max:500
     * }
     *
     * @response status=201 scenario="Success" {
     *   "status": "success",
     *   "message": "Report submitted successfully",
     *   "code": 201,
     *   "count": 1,
     *   "data": {
     *     "user_id": 1,
     *     "reportable_id": 42,
     *     "reportable_type": "App\\Models\\Post",
     *     "type": "post",
     *     "reason": "This post contains spam and inappropriate language.",
     *     "reportable_snapshot": {
     *       "user_id": 567,
     *       "title": "How to secure your API endpoints",
     *       "code": "const apiKey = process.env.API_KEY;",
     *       "description": "A guide to securing RESTful APIs using best practices.",
     *       "images": [
     *         "https://picsum.photos/id/1/200/300",
     *         "https://picsum.photos/id/926/200/300",
     *         "https://picsum.photos/id/27/200/300"
     *       ],
     *       "videos": [
     *         "https://www.youtube.com/watch?v=abcd1234",
     *         "https://www.youtube.com/watch?v=efgh5678"
     *       ],
     *       "resources": [
     *         "https://developer.mozilla.org/en-US/docs/Web/API",
     *         "https://laravel.com/docs/master"
     *       ],
     *       "external_source_previews": [
     *         {
     *           "url": "https://picsum.photos/id/1/200/300",
     *           "type": "images",
     *           "domain": "picsum.photos"
     *         },
     *         {
     *           "url": "https://www.youtube.com/watch?v=abcd1234",
     *           "type": "videos",
     *           "domain": "www.youtube.com"
     *         },
     *         {
     *           "url": "https://developer.mozilla.org/en-US/docs/Web/API",
     *           "type": "resources",
     *           "domain": "developer.mozilla.org"
     *         }
     *       ],
     *       "category": "Web Development",
     *       "post_type": "Tutorial",
     *       "status": "Published",
     *       "created_at": "2025-07-09T17:26:51.000000Z",
     *       "updated_at": "2025-07-09T17:28:12.000000Z",
     *       "tags": [
     *         { "id": 64, "name": "Security" },
     *         { "id": 65, "name": "API" }
     *       ],
     *       "languages": [
     *         { "id": 5, "name": "TypeScript" },
     *         { "id": 10, "name": "JavaScript" }
     *       ],
     *       "technologies": [
     *         { "id": 30, "name": "Express" },
     *         { "id": 44, "name": "Node.js" }
     *       ],
     *       "user_data": {
     *         "name": "Jane Doe",
     *         "email": "jane.doe@example.com",
     *         "role": "user"
     *       }
     *     },
     *     "impact_value": 5,
     *     "updated_at": "2025-07-13T20:21:35.000000Z",
     *     "created_at": "2025-07-13T20:21:35.000000Z",
     *     "id": 7
     *   }
     * }
     *
     * @response status=403 scenario="Cannot report own content" {
     *   "status": "error",
     *   "message": "You cannot report your own post",
     *   "code": 403,
     *   "errors": "CANNOT_REPORT_OWN_POST"
     * }
     *
     * @response status=409 scenario="Already reported" {
     *   "status": "error",
     *   "message": "You have already reported this post",
     *   "code": 409,
     *   "errors": "ALREADY_REPORTED"
     * }
     *
     * @response status=404 scenario="Entity not found" {
     *   "status": "error",
     *   "message": "Entity not found",
     *   "code": 404,
     *   "errors": "ENTITY_NOT_FOUND"
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
     * @authenticated
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
            return $this->errorResponse('Entity not found', 'ENTITY_NOT_FOUND', 404);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }

    /**
     * Remove a Report
     * 
     * Endpoint: DELETE /reports
     *
     * Removes a report from a post, comment, or user profile.
     * Regular users can only remove their own reports.
     * Administrators can remove any report by specifying the user_id.
     *
     * @group Report Management
     *
     * @bodyParam reportable_type string required The type of entity ('post', 'comment', or 'userProfile'). Example: comment
     * @bodyParam reportable_id integer required The ID of the entity. Example: 9
     * @bodyParam user_id integer optional For admins only: ID of the user whose report should be removed. If not provided, the authenticated user's report will be removed. Example: 3
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
     *   "errors": "REPORT_NOT_FOUND"
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
     * Note: Only administrators can remove reports for other users by specifying user_id. Regular users can only remove their own reports.
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
            return $this->errorResponse('Report not found', 'REPORT_NOT_FOUND', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 403);
        } catch (Exception $e) {
            return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
        }
    }
}
