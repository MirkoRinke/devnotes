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
            ],
            'filter' => [
                // Default
                ...['name', 'created_at', 'updated_at', 'email', 'email_verified_at'],
                // Basic
                ...['display_name', 'role'],
                // Ban info
                ...['is_banned', 'was_ever_banned'],
                // Moderation info
                ...['moderation_info'],
            ],
            'select' => [
                // Default
                ...['id', 'name', 'created_at', 'updated_at', 'email', 'email_verified_at'],
                // Basic
                ...['display_name', 'role'],
                // Ban info
                ...['is_banned', 'was_ever_banned'],
                // Moderation info
                ...['moderation_info'],
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
                // Settings
                ...['auto_load_external_images', 'external_images_temp_until', 'auto_load_external_videos', 'external_videos_temp_until', 'auto_load_external_resources', 'external_resources_temp_until'],
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
                ...['user_id', 'title', 'language', 'category', 'post_type', 'technology', 'tags', 'status'],
                // Counts
                ...['favorite_count', 'reports_count', 'likes_count'],
                // Update info
                ...['updated_at', 'is_updated', 'updated_by_role'],
                // Moderation info
                ...['moderation_info']
            ],
            'filter' => [
                // Default 
                ...['created_at'],
                // Basic
                ...['user_id', 'title', 'code', 'description', 'language', 'category', 'post_type', 'technology', 'tags', 'status'],
                // Counts
                ...['favorite_count', 'reports_count', 'likes_count'],
                // Update info
                ...['updated_at', 'is_updated', 'updated_by_role'],
                // Moderation info
                ...['moderation_info']
            ],
            'select' => [
                // Default 
                ...['id', 'created_at'],
                // Basic
                ...['user_id', 'title', 'code', 'description', 'resources', 'images', 'external_source_previews', 'language', 'category', 'post_type', 'technology', 'tags', 'status'],
                // Counts
                ...['favorite_count', 'reports_count', 'likes_count'],
                // Update info
                ...['updated_at', 'is_updated', 'updated_by_role'],
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
                ...['updated_at', 'is_updated', 'updated_by_role'],
                // Moderation info
                ...['moderation_info']
            ],
            'filter' => [
                // Default
                ...['created_at'],
                // Basic
                ...['post_id', 'user_id', 'content', 'parent_content', 'parent_id', 'is_deleted', 'depth'],
                // Counts
                ...['likes_count', 'reports_count'],
                // Update info
                ...['updated_at', 'is_updated', 'updated_by_role'],
                // Moderation info
                ...['moderation_info']
            ],
            'select' => [
                // Default
                ...['id', 'created_at'],
                // Basic
                ...['post_id', 'user_id', 'content', 'parent_content', 'parent_id', 'is_deleted', 'depth'],
                // Counts
                ...['likes_count', 'reports_count'],
                // Update info
                ...['updated_at', 'is_updated', 'updated_by_role'],
                // Moderation info
                ...['moderation_info']
            ],
            'getPerPage' => 10
        ],
        'user_favorites' => [
            'sort' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['user_id', 'post_id'],
            ],
            'filter' => [
                // Default
                ...['created_at', 'updated_at'],
                // Basic
                ...['user_id', 'post_id'],
            ],
            'select' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['user_id', 'post_id'],
            ],
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
                ...['user_id', 'reportable_id', 'reportable_type', 'type', 'reason', 'impact_value'],
            ],
            'filter' => [
                // Default
                ...['created_at', 'updated_at'],
                // Basic
                ...['user_id', 'reportable_id', 'reportable_type', 'type', 'reason', 'impact_value'],
            ],
            'select' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['user_id', 'reportable_id', 'reportable_type', 'type', 'reason', 'impact_value'],
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
