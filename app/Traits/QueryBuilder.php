<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

use App\Traits\ApiResponses;
use App\Traits\ApiSorting;
use App\Traits\ApiFiltering;
use App\Traits\ApiSelectable;
use App\Traits\ApiPagination;
use App\Traits\ApiStartsWith;
use App\Traits\ApiEndsWith;

/**
 * This QueryBuilder Trait provides methods to build queries for different models
 * based on the request parameters. It includes methods for sorting,
 * filtering, selecting fields, starting with, ending with, and pagination.
 * 
 */
trait QueryBuilder {

    /**
     *  The traits used in the Trait
     */
    use  ApiResponses, ApiSorting, ApiFiltering, ApiSelectable, ApiPagination, ApiStartsWith, ApiEndsWith;

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
            'startsWith' => [
                // Default
                ...['id', 'name', 'created_at', 'updated_at', 'email', 'email_verified_at'],
                // Basic
                ...['display_name', 'role'],
                // Ban info
                ...['is_banned', 'was_ever_banned'],
                // Moderation info
                ...['moderation_info'],
            ],
            'endsWith' => [
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
        'user_tokens' => [
            'sort' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'last_used_at']
            ],
            'filter' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'last_used_at']
            ],
            'select' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'last_used_at']
            ],
            'startsWith' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'last_used_at']
            ],
            'endsWith' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'last_used_at']
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
            'startsWith' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['user_id', 'display_name', 'public_email', 'website', 'avatar_path', 'is_public', 'location', 'skills', 'biography', 'social_links', 'contact_channels'],
                // Settings
                ...['auto_load_external_images', 'external_images_temp_until', 'auto_load_external_videos', 'external_videos_temp_until', 'auto_load_external_resources', 'external_resources_temp_until'],
                // Counts
                ...['reports_count'],
            ],
            'endsWith' => [
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
                ...['favorite_count', 'reports_count', 'likes_count', 'comments_count'],
                // Update info
                ...['updated_at', 'is_updated', 'updated_by_role', 'last_comment_at'],
                // Moderation info
                ...['moderation_info']
            ],
            'filter' => [
                // Default 
                ...['created_at'],
                // Basic
                ...['user_id', 'title', 'code', 'description', 'language', 'category', 'post_type', 'technology', 'tags', 'status'],
                // Counts
                ...['favorite_count', 'reports_count', 'likes_count', 'comments_count'],
                // Update info
                ...['updated_at', 'is_updated', 'updated_by_role', 'last_comment_at'],
                // Moderation info
                ...['moderation_info']
            ],
            'select' => [
                // Default 
                ...['id', 'created_at'],
                // Basic
                ...['user_id', 'title', 'code', 'description', 'resources', 'images', 'external_source_previews', 'language', 'category', 'post_type', 'technology', 'tags', 'status'],
                // Counts
                ...['favorite_count', 'reports_count', 'likes_count', 'comments_count'],
                // Update info
                ...['updated_at', 'is_updated', 'updated_by_role', 'last_comment_at'],
                // History
                ...['history'],
                // Moderation info
                ...['moderation_info']
            ],
            'startsWith' => [
                // Default 
                ...['id', 'created_at'],
                // Basic
                ...['user_id', 'title', 'code', 'description', 'resources', 'images', 'external_source_previews', 'language', 'category', 'post_type', 'technology', 'tags', 'status'],
                // Counts
                ...['favorite_count', 'reports_count', 'likes_count', 'comments_count'],
                // Update info
                ...['updated_at', 'is_updated', 'updated_by_role', 'last_comment_at'],
                // History
                ...['history'],
                // Moderation info
                ...['moderation_info']
            ],
            'endsWith' => [
                // Default 
                ...['id', 'created_at'],
                // Basic
                ...['user_id', 'title', 'code', 'description', 'resources', 'images', 'external_source_previews', 'language', 'category', 'post_type', 'technology', 'tags', 'status'],
                // Counts
                ...['favorite_count', 'reports_count', 'likes_count', 'comments_count'],
                // Update info
                ...['updated_at', 'is_updated', 'updated_by_role', 'last_comment_at'],
                // History
                ...['history'],
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
            'startsWith' => [
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
            'endsWith' => [
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
            'startsWith' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['user_id', 'post_id'],
            ],
            'endsWith' => [
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
            'startsWith' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['user_id', 'likeable_id', 'likeable_type', 'type'],
            ],
            'endsWith' => [
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
            'startsWith' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['user_id', 'reportable_id', 'reportable_type', 'type', 'reason', 'impact_value'],
            ],
            'endsWith' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['user_id', 'reportable_id', 'reportable_type', 'type', 'reason', 'impact_value'],
            ],
            'getPerPage' => 10
        ],
        'user_followers' => [
            'sort' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['user_id', 'follower_id'],
            ],
            'filter' => [
                // Default
                ...['created_at', 'updated_at'],
                // Basic
                ...['user_id', 'follower_id'],
            ],
            'select' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['user_id', 'follower_id'],
            ],
            'startsWith' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['user_id', 'follower_id'],
            ],
            'endsWith' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['user_id', 'follower_id'],
            ],
            'getPerPage' => 10
        ],
        'forbidden_names' => [
            'sort' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'match_type', 'created_by_role', 'created_by_user_id'],
            ],
            'filter' => [
                // Default
                ...['created_at', 'updated_at'],
                // Basic
                ...['name', 'match_type', 'created_by_role', 'created_by_user_id'],
            ],
            'select' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'match_type', 'created_by_role', 'created_by_user_id'],
            ],
            'startsWith' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'match_type', 'created_by_role', 'created_by_user_id'],
            ],
            'endsWith' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'match_type', 'created_by_role', 'created_by_user_id'],
            ],
            'getPerPage' => 10
        ],
        'post_allowed_values' => [
            'sort' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'type', 'created_by_role', 'created_by_user_id']
            ],
            'filter' => [
                // Default
                ...['created_at', 'updated_at'],
                // Basic
                ...['name', 'type', 'created_by_role', 'created_by_user_id']
            ],
            'select' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'type', 'created_by_role', 'created_by_user_id']
            ],
            'startsWith' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'type', 'created_by_role', 'created_by_user_id']
            ],
            'endsWith' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'type', 'created_by_role', 'created_by_user_id']
            ],
            'getPerPage' => 10
        ],
        'critical_terms' => [
            'sort' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'language', 'severity', 'created_by_role', 'created_by_user_id']
            ],
            'filter' => [
                // Default
                ...['created_at', 'updated_at'],
                // Basic
                ...['name', 'language', 'severity', 'created_by_role', 'created_by_user_id']
            ],
            'select' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'language', 'severity', 'created_by_role', 'created_by_user_id']
            ],
            'startsWith' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'language', 'severity', 'created_by_role', 'created_by_user_id']
            ],
            'endsWith' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'language', 'severity', 'created_by_role', 'created_by_user_id']
            ],
            'getPerPage' => 10
        ],
        'apiKey' => [
            'sort' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'active', 'last_used_at']
            ],
            'filter' => [
                // Default
                ...['created_at', 'updated_at'],
                // Basic
                ...['name', 'active', 'last_used_at']
            ],
            'select' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'active', 'last_used_at']
            ],
            'startsWith' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'active', 'last_used_at']
            ],
            'endsWith' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'active', 'last_used_at']
            ],
            'getPerPage' => 10
        ]
    ];

    /**
     * Build the query based on the request
     * 
     * @param Request $request
     * @param Builder $query
     * @param string $modelType
     * @return JsonResponse|Collection|LengthAwarePaginator
     * 
     * @example | $query = $this->buildQuery($request, $query, 'user');
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
     * 
     * @example | $config = $this->getQueryConfig('user', 'select');
     */
    protected function getQueryConfig(string $modelType, ?string $methodName = null): array|int|JsonResponse {
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
     * 
     * @example | $query = $this->select($request, $query, 'user');
     */
    protected function buildQuerySelect(Request $request, Builder $query, string $modelType): Builder|JsonResponse {
        $config = $this->getQueryConfig($modelType, 'select');
        if ($config instanceof JsonResponse) {
            return $config;
        }
        return $this->select($request, $query, (array) $config);
    }

    /**
     * Filter the query based on the request
     * 
     * @param Request $request
     * @param Builder $query
     * @param array $fields
     * @return Builder
     * 
     * @example | $query = $this->filter($request, $query, 'user');
     */
    protected function buildQueryFilter(Request $request, Builder $query, string $modelType): Builder|JsonResponse {
        $config = $this->getQueryConfig($modelType, 'filter');
        if ($config instanceof JsonResponse) {
            return $config;
        }
        return $this->filter($request, $query, (array) $config);
    }

    /**
     * Sort the query based on the request
     * 
     * @param Request $request
     * @param Builder $query
     * @param array $fields
     * @return Builder
     * 
     * @example | $query = $this->sort($request, $query, 'user');
     */
    protected function buildQuerySort(Request $request, Builder $query, string $modelType): Builder|JsonResponse {
        $config = $this->getQueryConfig($modelType, 'sort');
        if ($config instanceof JsonResponse) {
            return $config;
        }
        return $this->sort($request, $query, (array)$config);
    }


    /**
     * startWith the query based on the request
     * 
     * @param Request $request
     * @param Builder $query
     * @param array $fields
     * @return Builder
     * 
     * @example | $query = $this->startsWith($request, $query, 'user');
     */
    protected function buildQueryStartsWith(Request $request, Builder $query, string $modelType): Builder|JsonResponse {
        $config = $this->getQueryConfig($modelType, 'startsWith');
        if ($config instanceof JsonResponse) {
            return $config;
        }
        return $this->startsWith($request, $query, (array) $config);
    }


    /**
     * endWith the query based on the request
     * 
     * @param Request $request
     * @param Builder $query
     * @param array $fields
     * @return Builder
     * 
     * @example | $query = $this->endsWith($request, $query, 'user');
     */
    protected function buildQueryEndsWith(Request $request, Builder $query, string $modelType): Builder|JsonResponse {
        $config = $this->getQueryConfig($modelType, 'endsWith');
        if ($config instanceof JsonResponse) {
            return $config;
        }
        return $this->endsWith($request, $query, (array) $config);
    }

    /**
     * Paginate the query based on the request
     * 
     * @param Request $request
     * @param Builder $query
     * @param int $perPage
     * @return Builder
     * 
     * @example | $query = $this->paginate($request, $query, 'user');
     */
    protected function buildQueryPaginate(Request $request, Builder $query, string $modelType): Builder|JsonResponse {
        $config = $this->getQueryConfig($modelType, 'getPerPage');
        if ($config instanceof JsonResponse) {
            return $config;
        }
        return $this->getPerPage($request, $query, (int) $config);
    }
}
