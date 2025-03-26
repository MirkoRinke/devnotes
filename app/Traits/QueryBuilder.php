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
            'sort' => ['id', 'name', 'display_name', 'email', 'created_at', 'updated_at', 'is_banned', 'banned_at', 'unbanned_at', 'banned_by', 'unbanned_by'],
            'filter' => ['name', 'display_name', 'email', 'created_at', 'updated_at', 'is_banned', 'banned_at', 'unbanned_at', 'banned_by', 'unbanned_by'],
            'select' => ['id', 'name', 'display_name', 'email', 'created_at', 'updated_at', 'is_banned', 'banned_at', 'unbanned_at', 'banned_by', 'unbanned_by'],
            'getPerPage' => 10
        ],
        'user_profile' => [
            'sort' => ['id', 'user_id', 'display_name', 'location', 'created_at', 'updated_at', 'is_public'],
            'filter' => ['user_id', 'display_name', 'location', 'skills', 'is_public'],
            'select' => ['id', 'user_id', 'display_name', 'location', 'skills', 'biography', 'social_links', 'website', 'avatar_path', 'is_public', 'created_at', 'updated_at'],
            'getPerPage' => 10
        ],
        'post' => [
            'sort' => ['id', 'user_id', 'title', 'language', 'category', 'tags', 'status', 'favorite_count', 'created_at', 'updated_at'],
            'filter' => ['title', 'user_id', 'language', 'category', 'tags', 'status', 'created_at', 'updated_at'],
            'select' => ['id', 'user_id', 'title', 'code', 'description', 'resources', 'language', 'category', 'tags', 'status', 'favorite_count', 'reports_count', 'created_at', 'updated_at'],
            'getPerPage' => 10
        ],
        'comment' => [
            'sort' => ['id', 'post_id', 'user_id', 'is_deleted', 'is_edited', 'edited_at', 'likes_count', 'reports_count', 'created_at', 'updated_at'],
            'filter' => ['post_id', 'user_id', 'parent_id', 'is_deleted', 'is_edited', 'edited_at', 'likes_count', 'reports_count', 'created_at', 'updated_at'],
            'select' => ['id', 'post_id', 'user_id', 'content', 'parent_id', 'is_deleted', 'is_edited', 'edited_at', 'likes_count', 'reports_count', 'created_at', 'updated_at'],
            'getPerPage' => 10
        ],
        'favorite' => [
            'sort' =>  ['id', 'user_id', 'post_id', 'created_at', 'updated_at'],
            'filter' => ['id', 'user_id', 'post_id', 'created_at', 'updated_at'],
            'select' =>  ['id', 'user_id', 'post_id', 'created_at', 'updated_at'],
            'getPerPage' => 10
        ],
        'like' => [
            'sort' => ['id', 'user_id', 'likeable_id', 'likeable_type', 'type', 'created_at', 'updated_at'],
            'filter' => ['user_id', 'likeable_id', 'likeable_type', 'type', 'created_at', 'updated_at'],
            'select' => ['id', 'user_id', 'likeable_id', 'likeable_type', 'type', 'created_at', 'updated_at'],
            'getPerPage' => 10
        ],
        'report' => [
            'sort' => ['id', 'user_id', 'reportable_id', 'reportable_type', 'type', 'created_at', 'updated_at'],
            'filter' => ['user_id', 'reportable_id', 'reportable_type', 'type', 'created_at', 'updated_at'],
            'select' => ['id', 'user_id', 'reportable_id', 'reportable_type', 'type', 'reason', 'created_at', 'updated_at'],
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
