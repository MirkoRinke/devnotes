<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

use \App\Traits\ApiResponses; // example return $this->successResponse($posts, 'Posts retrieved successfully', 200);

trait QueryBuilder {
    /**
     *  The traits used in the controller
     */
    use ApiResponses;

    /**
     * Predefined query methods for different model types
     */
    protected $queryConfigurations = [
        'user' => [
            'sort' => [
                // Default
                ...['id', 'name', 'created_at', 'updated_at', 'email', 'email_verified_at'],
                // Basic
                ...['display_name', 'role'],
                // Ban info
                ...['is_banned', 'banned_at', 'unbanned_at', 'banned_by', 'unbanned_by'],
                // Moderation info
            ],
            'filter' => [
                // Default
                ...['name', 'created_at', 'updated_at', 'email', 'email_verified_at'],
                // Basic
                ...['display_name', 'role'],
                // Ban info
                ...['is_banned', 'banned_at', 'unbanned_at', 'banned_by', 'unbanned_by'],
                // Moderation info
                ...['ban_reason', 'unban_reason']
            ],
            'select' => [
                // Default
                ...['id', 'name', 'created_at', 'updated_at', 'email', 'email_verified_at'],
                // Basic
                ...['display_name', 'role'],
                // Ban info
                ...['is_banned', 'banned_at', 'unbanned_at', 'banned_by', 'unbanned_by'],
                // Moderation info
                ...['ban_reason', 'unban_reason']
            ],
            'getPerPage' => 10
        ],
        'user_profile' => [
            'sort' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['user_id', 'display_name', 'is_public', 'location'],
                // Counts
                ...['reports_count'],
            ],
            'filter' => [
                // Default
                ...['created_at', 'updated_at'],
                // Basic
                ...['user_id', 'display_name', 'is_public', 'location',],
                // Counts
                ...['reports_count'],
            ],
            'select' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['user_id', 'display_name', 'public_email', 'website', 'avatar_path', 'is_public', 'location', 'skills', 'biography', 'social_links', 'contact_channels'],
                // Counts
                ...['reports_count'],
            ],
            'getPerPage' => 10
        ],
        'post' => [
            'sort' => [
                // Default 
                ...['id', 'created_at'],
                // Basic
                ...['user_id', 'title', 'language', 'category', 'tags', 'status'],
                // Counts
                ...['favorite_count', 'reports_count', 'likes_count'],
                // Update info
                ...['updated_at', 'is_edited', 'updated_by', 'updated_by_role'],
                // Moderation info
                ...['moderation_info']
            ],
            'filter' => [
                // Default 
                ...['created_at'],
                // Basic
                ...['user_id', 'title', 'code', 'description', 'language', 'category', 'tags', 'status'],
                // Counts
                ...['favorite_count', 'reports_count', 'likes_count'],
                // Update info
                ...['updated_at', 'is_edited', 'updated_by', 'updated_by_role'],
                // Moderation info
                ...['moderation_info']
            ],
            'select' => [
                // Default 
                ...['id', 'created_at'],
                // Basic
                ...['user_id', 'title', 'code', 'description', 'resources', 'language', 'category', 'tags', 'status'],
                // Counts
                ...['favorite_count', 'reports_count', 'likes_count'],
                // Update info
                ...['updated_at', 'is_edited', 'updated_by', 'updated_by_role'],
                // Moderation info
                ...['moderation_info']
            ],
            'getPerPage' => 10
        ],
        'comment' => [
            'sort' => [
                // Default
                ...['id', 'created_at'],
                // Basic
                ...['post_id', 'user_id', 'parent_id', 'is_deleted', 'depth'],
                // Counts
                ...['likes_count', 'reports_count'],
                // Update info
                ...['updated_at', 'is_edited', 'updated_by', 'updated_by_role'],
                // Moderation info
                ...['moderation_info']
            ],
            'filter' => [
                // Default
                ...['created_at'],
                // Basic
                ...['post_id', 'user_id', 'content', 'parent_id', 'is_deleted', 'depth'],
                // Counts
                ...['likes_count', 'reports_count'],
                // Update info
                ...['updated_at', 'is_edited', 'updated_by', 'updated_by_role'],
                // Moderation info
                ...['moderation_info']
            ],
            'select' => [
                // Default
                ...['id', 'created_at'],
                // Basic
                ...['post_id', 'user_id', 'content', 'parent_id', 'is_deleted', 'depth'],
                // Counts
                ...['likes_count', 'reports_count'],
                // Update info
                ...['updated_at', 'is_edited', 'updated_by', 'updated_by_role'],
                // Moderation info
                ...['moderation_info']
            ],
            'getPerPage' => 10
        ],
        'user_favorites' => [
            'sort' =>  ['id', 'user_id', 'post_id', 'created_at', 'updated_at'],
            'filter' => ['user_id', 'post_id', 'created_at', 'updated_at'],
            'select' =>  ['id', 'user_id', 'post_id', 'created_at', 'updated_at'],
            'getPerPage' => 10
        ],
        'like' => [
            'sort' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['user_id', 'likeable_id', 'likeable_type', 'type'],
            ],
            'filter' => [
                // Default
                ...['created_at', 'updated_at'],
                // Basic
                ...['user_id', 'likeable_id', 'likeable_type', 'type'],
            ],
            'select' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['user_id', 'likeable_id', 'likeable_type', 'type'],
            ],
            'getPerPage' => 10
        ],
        'user_reports' => [
            'sort' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['user_id', 'reportable_id', 'reportable_type', 'type', 'reason'],
            ],
            'filter' => [
                // Default
                ...['created_at', 'updated_at'],
                // Basic
                ...['user_id', 'reportable_id', 'reportable_type', 'type', 'reason'],
            ],
            'select' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['user_id', 'reportable_id', 'reportable_type', 'type', 'reason'],
            ],
            'getPerPage' => 10
        ],
    ];

    /**
     * Build the query based on the request
     * 
     * @param Request $request
     * @param Builder $query
     * @param string $modelType
     * @return JsonResponse|Collection|LengthAwarePaginator
     */
    private function buildQuery(Request $request, Builder $query, string $modelType): JsonResponse|Collection|LengthAwarePaginator {
        if (!isset($this->queryConfigurations[$modelType])) {
            return $this->errorResponse("Query configuration for '{$modelType}' is not defined", 'QUERY_CONFIG_NOT_DEFINED', 500);
        }

        $methods = $this->queryConfigurations[$modelType];

        foreach ($methods as $method => $params) {
            $query = $this->$method($request, $query, $params);
            if ($query instanceof JsonResponse) {
                return $query;
            }
        }
        return $query;
    }

    /**
     * Select fields to include in the response
     * 
     * @param Request $request
     * @param Builder $query
     * @param array $fields
     * @return Builder
     */
    protected function getQueryConfig(string $modelType, ?string $methodName = null): array|JsonResponse {
        if (!isset($this->queryConfigurations[$modelType])) {
            return $this->errorResponse("Query configuration for '{$modelType}' is not defined", 'QUERY_CONFIG_NOT_DEFINED', 500);
        }

        if ($methodName !== null) {
            if (!isset($this->queryConfigurations[$modelType][$methodName])) {
                return $this->errorResponse("Query configuration for '{$modelType}' with method '{$methodName}' is not defined", 'QUERY_CONFIG_NOT_DEFINED', 500);
            }
            return $this->queryConfigurations[$modelType][$methodName];
        }
        return $this->queryConfigurations[$modelType];
    }

    /**
     * Select fields to include in the response
     * 
     * @param Request $request
     * @param Builder $query
     * @param array $fields
     * @return Builder
     */
    protected function buildQuerySelect(Request $request, Builder $query, string $modelType): Builder|JsonResponse {
        $config = $this->getQueryConfig($modelType, 'select');
        if ($config instanceof JsonResponse) {
            return $config;
        }
        return $this->select($request, $query, $config);
    }

    /**
     * Filter the query based on the request
     * 
     * @param Request $request
     * @param Builder $query
     * @param array $fields
     * @return Builder
     */
    protected function buildQueryFilter(Request $request, Builder $query, string $modelType): Builder|JsonResponse {
        $config = $this->getQueryConfig($modelType, 'filter');
        if ($config instanceof JsonResponse) {
            return $config;
        }
        return $this->filter($request, $query, $config);
    }

    /**
     * Sort the query based on the request
     * 
     * @param Request $request
     * @param Builder $query
     * @param array $fields
     * @return Builder
     */
    protected function buildQuerySort(Request $request, Builder $query, string $modelType): Builder|JsonResponse {
        $config = $this->getQueryConfig($modelType, 'sort');
        if ($config instanceof JsonResponse) {
            return $config;
        }
        return $this->sort($request, $query, $config);
    }
}
