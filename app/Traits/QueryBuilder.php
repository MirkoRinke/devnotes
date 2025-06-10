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

/**
 * This QueryBuilder Trait provides methods to build queries for different models
 * based on the request parameters. It includes methods for sorting, filtering, selecting fields, and pagination.
 */
trait QueryBuilder {

    /**
     *  The traits used in the Trait
     */
    use  ApiResponses, ApiSorting, ApiFiltering, ApiSelectable, ApiPagination;

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
                ...['id', 'name', 'created_at', 'updated_at', 'email', 'email_verified_at'],
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
                ...['id', 'created_at', 'updated_at'],
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
                ...['user_id', 'title', 'language', 'category', 'post_type', 'technology', 'status'],
                // Counts
                ...['favorite_count', 'reports_count', 'likes_count', 'comments_count'],
                // Update info
                ...['updated_at', 'is_updated', 'updated_by_role', 'last_comment_at'],
                // Moderation info
                ...['moderation_info']
            ],
            'filter' => [
                // Default 
                ...['id', 'created_at'],
                // Basic
                ...['user_id', 'title', 'code', 'description', 'language', 'category', 'post_type', 'technology', 'status'],
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
                ...['user_id', 'title', 'code', 'description', 'resources', 'images', 'external_source_previews', 'language', 'category', 'post_type', 'technology', 'status'],
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
                ...['id', 'created_at', 'updated_at'],
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
                ...['id', 'created_at', 'updated_at'],
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
                ...['id', 'created_at', 'updated_at'],
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
        'user_followers' => [
            'sort' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['user_id', 'follower_id'],
            ],
            'filter' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['user_id', 'follower_id'],
            ],
            'select' => [
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
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'match_type', 'created_by_role', 'created_by_user_id'],
            ],
            'select' => [
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
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'type', 'created_by_role', 'created_by_user_id']
            ],
            'select' => [
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
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'language', 'severity', 'created_by_role', 'created_by_user_id']
            ],
            'select' => [
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
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'active', 'last_used_at']
            ],
            'select' => [
                // Default
                ...['id', 'created_at', 'updated_at'],
                // Basic
                ...['name', 'active', 'last_used_at']
            ],
            'getPerPage' => 10
        ]
    ];


    /**
     * Get relation filters based on the request and model type
     * 
     * @param Request $request The HTTP request containing all query parameters
     * @param string $modelType The model type to get relation filters for
     * @return array The relation filters for the specified model type
     * 
     * @example | $filters = $this->getRelationFilters($request, 'post');
     */
    protected function getRelationFilters(Request $request, $modelType): array {

        /**
         * Predefined relation filters for different model types
         * 
         */
        $relationFilters = [
            'post' => [
                'tags' => [
                    'id',
                    'name',
                ],
                // 'language' => [
                //     'id',
                //     'name',
                // ],
                // 'technology' => [
                //     'id',
                //     'name',
                // ],
            ],
        ];

        /**
         * Dynamic relations that can be included based on the request
         */
        $dynamicRelations = [
            'post' => [
                'user' => [
                    'id',
                    'display_name',
                    'role',
                    'created_at',
                    'updated_at',
                    'is_banned',
                    'was_ever_banned',
                    'moderation_info'
                ]
            ],
            'user' => [
                'profile' => [
                    'id',
                    'user_id',
                    'display_name',
                    'public_email',
                    'website',
                    'avatar_path',
                    'is_public',
                    'location',
                    'biography',
                    'skills',
                    'social_links',
                    'contact_channels',
                    'auto_load_external_images',
                    'external_images_temp_until',
                    'auto_load_external_videos',
                    'external_videos_temp_until',
                    'auto_load_external_resources',
                    'external_resources_temp_until',
                    'reports_count',
                    'created_at',
                    'updated_at'
                ]
            ]
        ];

        /**
         * Check if the request has 'include' parameter and if 'user' is included
         */
        $userRelationModels = ['post'];
        if ($request->has('include') && in_array('user', explode(',', $request->input('include'))) && in_array($modelType, $userRelationModels)) {
            if (!isset($relationFilters[$modelType])) {
                $relationFilters[$modelType] = [];
            }
            $relationFilters[$modelType] = array_merge_recursive($relationFilters[$modelType], $dynamicRelations[$modelType]);
        }


        /**
         * Check if the request has 'include' parameter and if 'profile' is included
         */
        $profileRelationModels = ['user'];
        if ($request->has('include') && in_array('profile', explode(',', $request->input('include'))) && in_array($modelType, $profileRelationModels)) {
            if (!isset($relationFilters[$modelType])) {
                $relationFilters[$modelType] = [];
            }
            $relationFilters[$modelType] = array_merge_recursive($relationFilters[$modelType], $dynamicRelations[$modelType]);
        }

        return $relationFilters[$modelType] ?? [];
    }


    /**
     * Build the query based on the request
     * 
     * @param Request $request The HTTP request containing all query parameters
     * @param Builder $query The query builder to modify
     * @param string $modelType The model type to build the query for
     * @return JsonResponse|Collection|LengthAwarePaginator The query result or error response
     * 
     * @example | $query = $this->buildQuery($request, $query, 'user');
     */
    private function buildQuery(Request $request, Builder $query, string $modelType): JsonResponse|Collection|LengthAwarePaginator {
        if (!isset($this->queryConfigurations[$modelType])) {
            return $this->errorResponse("Query configuration for '{$modelType}' is not defined", 'QUERY_CONFIG_NOT_DEFINED', 500);
        }

        $methods = $this->queryConfigurations[$modelType] ?? [];
        $relations = $this->getRelationFilters($request, $modelType);

        foreach ($methods as $method => $params) {
            if (in_array($method, ['filter'])) {
                $query = $this->$method($request, $query, $params, $relations);
            } else {
                $query = $this->$method($request, $query, $params);
            }

            if ($query instanceof JsonResponse) {
                return $query;
            }
        }

        return $query;
    }

    /**
     * Get the query configuration for a specific model type and method
     * 
     * @param string $modelType The model type to get the configuration for
     * @param string|null $methodName The specific method to get configuration for (null for all methods)
     * @return array|int|JsonResponse The configuration or an error response
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
     * Select specific fields in the query based on the request
     * 
     * @param Request $request The HTTP request containing select parameters
     * @param Builder $query The query builder to apply selection to
     * @param string $modelType The model type to get the select configuration for
     * @return Builder|JsonResponse The query with selection applied or an error response
     * 
     * @example | $query = $this->buildQuerySelect($request, $query, 'post');
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
     * @param Request $request The HTTP request containing filter parameters
     * @param Builder $query The query builder to apply filters to
     * @param string $modelType The model type to get the filter configuration for
     * @return Builder|JsonResponse The filtered query or an error response
     * 
     * @example | $query = $this->buildQueryFilter($request, $query, 'post');
     */
    protected function buildQueryFilter(Request $request, Builder $query, string $modelType): Builder|JsonResponse {
        $config = $this->getQueryConfig($modelType, 'filter');
        $relations = $this->relationFilters[$modelType] ?? [];

        if ($config instanceof JsonResponse) {
            return $config;
        }
        return $this->filter($request, $query, (array) $config, $relations);
    }


    /**
     * Sort the query based on the request
     * 
     * @param Request $request The HTTP request containing sort parameters
     * @param Builder $query The query builder to apply sorting to
     * @param string $modelType The model type to get the sort configuration for
     * @return Builder|JsonResponse The sorted query or an error response
     * 
     * @example | $query = $this->buildQuerySort($request, $query, 'post');
     */
    protected function buildQuerySort(Request $request, Builder $query, string $modelType): Builder|JsonResponse {
        $config = $this->getQueryConfig($modelType, 'sort');
        if ($config instanceof JsonResponse) {
            return $config;
        }
        return $this->sort($request, $query, (array)$config);
    }


    /**
     * Apply pagination to the query based on the request
     * 
     * @param Request $request The HTTP request containing pagination parameters
     * @param Builder $query The query builder to apply pagination to
     * @param string $modelType The model type to get the pagination configuration for
     * @return Builder|JsonResponse The paginated query or an error response
     * 
     * @example | $query = $this->buildQueryPaginate($request, $query, 'post');
     */
    protected function buildQueryPaginate(Request $request, Builder $query, string $modelType): Builder|JsonResponse {
        $config = $this->getQueryConfig($modelType, 'getPerPage');
        if ($config instanceof JsonResponse) {
            return $config;
        }
        return $this->getPerPage($request, $query, (int) $config);
    }
}
