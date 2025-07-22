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
use App\Traits\AuthHelper;
use App\Traits\PolicyChecks;
use App\Traits\ApiLimit;

/**
 * This QueryBuilder Trait provides methods to build queries for different models
 * based on the request parameters. It includes methods for sorting, filtering, selecting fields, and pagination.
 */
trait QueryBuilder {

    /**
     *  The traits used in the Trait
     */
    use  ApiResponses, ApiSorting, ApiFiltering, ApiSelectable, ApiPagination, ApiLimit, AuthHelper, PolicyChecks;


    /**
     * The query configurations for different models
     */
    protected function queryConfigurations(Request $request, $modelType, $methodName = null): array {
        $hasModeratorPrivileges = $this->hasModeratorPrivileges($this->getAuthenticatedUser($request));

        $queryConfigurations = [
            'user' => [
                'sort' => [
                    // Default
                    ...['id', 'name', 'created_at', 'updated_at', 'email', 'email_verified_at'],
                    // Basic
                    ...['display_name', 'role'],
                    // Ban info
                    ...($hasModeratorPrivileges ? ['is_banned', 'was_ever_banned'] : []),
                ],
                'filter' => [
                    // Default
                    ...['id', 'name', 'created_at', 'updated_at', 'email', 'email_verified_at'],
                    // Basic
                    ...['display_name', 'role'],
                    // Ban info
                    ...($hasModeratorPrivileges ? ['is_banned', 'was_ever_banned'] : []),
                    // Moderation info
                    ...($hasModeratorPrivileges ? ['moderation_info'] : []),
                ],
                'select' => [
                    // Default
                    ...['id', 'name', 'created_at', 'updated_at', 'email', 'email_verified_at'],
                    // Basic
                    ...['display_name', 'role'],
                    // Ban info
                    ...($hasModeratorPrivileges ? ['is_banned', 'was_ever_banned'] : []),
                    // Moderation info
                    ...($hasModeratorPrivileges ? ['moderation_info'] : []),
                ],
                'setLimit' => 10,
                'paginate' => 10
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
                    ...['name', 'last_used_at'],
                    // Status Flags
                    ...['is_current']
                ],
                'setLimit' => 10,
                'paginate' => 10
            ],
            'user_profile' => [
                'sort' => [
                    // Default
                    ...['id', 'created_at', 'updated_at'],
                    // Basic
                    ...['user_id', 'display_name', 'is_public', 'location'],
                    // Counts
                    ...($hasModeratorPrivileges ? ['reports_count'] : []),
                ],
                'filter' => [
                    // Default
                    ...['id', 'created_at', 'updated_at'],
                    // Basic
                    ...['user_id', 'display_name', 'public_email', 'website', 'avatar_path', 'is_public', 'location', 'skills', 'biography', 'social_links', 'contact_channels'],
                    // Settings
                    ...['auto_load_external_images', 'external_images_temp_until', 'auto_load_external_videos', 'external_videos_temp_until', 'auto_load_external_resources', 'external_resources_temp_until'],
                    // Counts
                    ...($hasModeratorPrivileges ? ['reports_count'] : []),
                ],
                'select' => [
                    // Default
                    ...['id', 'created_at', 'updated_at'],
                    // Basic
                    ...['user_id', 'display_name', 'public_email', 'website', 'avatar_path', 'is_public', 'location', 'skills', 'biography', 'social_links', 'contact_channels'],
                    // Settings
                    ...['auto_load_external_images', 'external_images_temp_until', 'auto_load_external_videos', 'external_videos_temp_until', 'auto_load_external_resources', 'external_resources_temp_until'],
                    // Counts
                    ...($hasModeratorPrivileges ? ['reports_count'] : []),
                ],
                'setLimit' => 10,
                'paginate' => 10
            ],
            'post' => [
                'sort' => [
                    // Default 
                    ...['id', 'created_at'],
                    // Basic
                    ...['user_id', 'title',  'category', 'post_type', 'status'],
                    // Counts
                    ...['favorite_count', 'likes_count', 'comments_count'],
                    ...($hasModeratorPrivileges ? ['reports_count'] : []),
                    // Update info
                    ...['updated_at', 'is_updated', 'updated_by_role', 'comments_updated_at'],
                ],
                'filter' => [
                    // Default 
                    ...['id', 'created_at'],
                    // Basic
                    ...['user_id', 'title', 'code', 'description', 'resources', 'images', 'videos', 'external_source_previews', 'category', 'post_type', 'status'],
                    // Counts
                    ...['favorite_count', 'likes_count', 'comments_count'],
                    ...($hasModeratorPrivileges ? ['reports_count'] : []),
                    // Update info
                    ...['updated_at', 'is_updated', 'updated_by_role', 'comments_updated_at'],
                    // History
                    ...['history'],
                    // Moderation info
                    ...($hasModeratorPrivileges ? ['moderation_info'] : []),
                ],
                'select' => [
                    // Default 
                    ...['id', 'created_at'],
                    // Basic
                    ...['user_id', 'title', 'code', 'description', 'resources', 'images', 'videos', 'external_source_previews', 'category', 'post_type', 'status'],
                    // Counts
                    ...['favorite_count', 'likes_count', 'comments_count'],
                    ...($hasModeratorPrivileges ? ['reports_count'] : []),
                    // Update info
                    ...['updated_at', 'is_updated', 'updated_by_role', 'comments_updated_at'],
                    // History
                    ...['history'],
                    // Moderation info
                    ...($hasModeratorPrivileges ? ['moderation_info'] : []),
                    // Relationship Status Flags
                    ...['is_favorited', 'is_liked'],
                ],
                'setLimit' => 10,
                'paginate' => 10
            ],
            'comment' => [
                'sort' => [
                    // Default
                    ...['id', 'created_at'],
                    // Basic
                    ...['post_id', 'user_id', 'parent_id', 'is_deleted', 'depth'],
                    // Counts
                    ...['likes_count'],
                    ...($hasModeratorPrivileges ? ['reports_count'] : []),
                    // Update info
                    ...['updated_at', 'is_updated', 'updated_by_role'],
                ],
                'filter' => [
                    // Default
                    ...['id', 'created_at'],
                    // Basic
                    ...['post_id', 'user_id', 'content', 'parent_content', 'parent_id', 'is_deleted', 'depth'],
                    // Counts
                    ...['likes_count'],
                    ...($hasModeratorPrivileges ? ['reports_count'] : []),
                    // Update info
                    ...['updated_at', 'is_updated', 'updated_by_role'],
                    // Moderation info
                    ...($hasModeratorPrivileges ? ['moderation_info'] : []),
                ],
                'select' => [
                    // Default
                    ...['id', 'created_at'],
                    // Basic
                    ...['post_id', 'user_id', 'content', 'parent_content', 'parent_id', 'is_deleted', 'depth'],
                    // Counts
                    ...['likes_count'],
                    ...($hasModeratorPrivileges ? ['reports_count'] : []),
                    // Update info
                    ...['updated_at', 'is_updated', 'updated_by_role'],
                    // Moderation info
                    ...($hasModeratorPrivileges ? ['moderation_info'] : []),
                    // Relationship Status Flags
                    ...['is_liked'],
                ],
                'setLimit' => 10,
                'paginate' => 10
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
                'setLimit' => 10,
                'paginate' => 10
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
                'setLimit' => 10,
                'paginate' => 10
            ],
            'user_reports' => [
                'sort' => [
                    // Default
                    ...['id', 'created_at', 'updated_at'],
                    // Basic
                    ...['user_id', 'reportable_id', 'reportable_type', 'type', 'impact_value'],
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
                'setLimit' => 10,
                'paginate' => 10
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
                'setLimit' => 10,
                'paginate' => 10
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
                'setLimit' => 10,
                'paginate' => 10
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
                'setLimit' => 10,
                'paginate' => 10
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
                'setLimit' => 10,
                'paginate' => 10
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
                'setLimit' => 10,
                'paginate' => 10
            ]
        ];

        if ($methodName) {
            return $queryConfigurations[$modelType][$methodName] ?? [];
        }
        return $queryConfigurations[$modelType] ?? [];
    }

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
                'languages' => [
                    'id',
                    'name',
                ],
                'technologies' => [
                    'id',
                    'name',
                ],
            ],
            'user_profile' => [
                'favorite_languages' => [
                    'id',
                    'name',
                ],
            ],
        ];

        /**
         * Dynamic relations that can be included based on the request
         */
        $dynamicRelations = [
            'user' => [
                'profile' => $this->queryConfigurations($request, 'user_profile', 'filter'),
            ],
            'user_profile' => [
                'user' => $this->queryConfigurations($request, 'user', 'filter'),
            ],
            'post' => [
                'user' => $this->queryConfigurations($request, 'user', 'filter'),
            ],
            'comment' => [
                'user' => $this->queryConfigurations($request, 'user', 'filter'),
                'parent' => $this->queryConfigurations($request, 'comment', 'filter'),
                'children' => $this->queryConfigurations($request, 'comment', 'filter'),
            ],
            'user_reports' => [
                'user' => $this->queryConfigurations($request, 'user', 'filter'),
                // 'reportable' The polymorphic relation is not supported in this context.
            ],
            'like' => [
                'user' => $this->queryConfigurations($request, 'user', 'filter'),
                // 'likeable' The polymorphic relation is not supported in this context.
            ],
            'user_followers' => [
                'user' => $this->queryConfigurations($request, 'user', 'filter'),
                'follower' => $this->queryConfigurations($request, 'user', 'filter'),
            ],
            'forbidden_names' => [
                'user' => $this->queryConfigurations($request, 'user', 'filter'),
            ],
            'post_allowed_values' => [
                'user' => $this->queryConfigurations($request, 'user', 'filter'),
            ],
            'critical_terms' => [
                'user' => $this->queryConfigurations($request, 'user', 'filter'),
            ],
        ];


        /**
         * Check if the request has 'include' parameter and if 'user' is included
         */
        $userRelationModels = ['post', 'user_profile', 'comment', 'user_reports', 'like', 'user_followers', 'forbidden_names', 'post_allowed_values', 'critical_terms'];
        if ($request->has('include') && in_array('user', explode(',', $request->input('include'))) && in_array($modelType, $userRelationModels)) {
            if (!isset($relationFilters[$modelType])) {
                $relationFilters[$modelType] = [];
            }
            $relationFilters[$modelType] = array_merge_recursive($relationFilters[$modelType], $dynamicRelations[$modelType]);
        }


        /**
         * Check if the request has 'include' parameter and if 'follower' is included
         */
        $followerRelationModels = ['user_followers'];
        if ($request->has('include') && in_array('follower', explode(',', $request->input('include'))) && in_array($modelType, $followerRelationModels)) {
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

        /**
         * Check if the request has 'include' parameter and if 'parent' is included
         */
        $parentRelationModels = ['comment'];
        if ($request->has('include') && in_array('parent', explode(',', $request->input('include'))) && in_array($modelType, $parentRelationModels)) {
            if (!isset($relationFilters[$modelType])) {
                $relationFilters[$modelType] = [];
            }
            $relationFilters[$modelType] = array_merge_recursive($relationFilters[$modelType], $dynamicRelations[$modelType]);
        }

        /**
         * Check if the request has 'include' parameter and if 'children' is included
         */
        $childrenRelationModels = ['comment'];
        if ($request->has('include') && in_array('children', explode(',', $request->input('include'))) && in_array($modelType, $childrenRelationModels)) {
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
        $methods = $this->getQueryConfig($request, $modelType);
        if ($methods instanceof JsonResponse) {
            return $methods; // Return error response if configuration is not defined
        }

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
    protected function getQueryConfig(Request $request, string $modelType, ?string $methodName = null): array|int|JsonResponse {
        if ($this->queryConfigurations($request, $modelType) === []) {
            return $this->errorResponse("Query configuration for '{$modelType}' is not defined", 'QUERY_CONFIG_NOT_DEFINED', 500);
        }

        if ($methodName !== null) {
            if ($this->queryConfigurations($request, $modelType, $methodName) === []) {
                return $this->errorResponse("Query configuration for '{$modelType}' with method '{$methodName}' is not defined", 'QUERY_CONFIG_NOT_DEFINED', 500);
            }
            return $this->queryConfigurations($request, $modelType, $methodName);
        }
        return $this->queryConfigurations($request, $modelType);
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
        $config = $this->getQueryConfig($request, $modelType, 'select');
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
        $config = $this->getQueryConfig($request, $modelType, 'filter');
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
        $config = $this->getQueryConfig($request, $modelType, 'sort');
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
        $config = $this->getQueryConfig($request, $modelType, 'paginate');
        if ($config instanceof JsonResponse) {
            return $config;
        }
        return $this->paginate($request, $query, (int) $config);
    }
}
