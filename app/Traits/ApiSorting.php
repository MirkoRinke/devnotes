<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Traits\ApiResponses;

/**
 * This ApiSorting Trait provides a method to sort query results based on request parameters.
 * It checks if the sort parameters are valid and applies the appropriate order direction to the query.
 */
trait ApiSorting {

    /**
     *  The traits used in the Trait
     */
    use ApiResponses;

    /**
     * Sort the query results based on the request
     *
     * @param Request $request
     * @param Builder $query
     * @param array $allowedColumns
     * @return Builder
     * 
     * @example | $this->sort($request, $query, (array)$config);
     */
    public function sort(Request $request, Builder $query, array $allowedColumns = []): JsonResponse|Builder {
        // Get the page parameter from the request
        $sort = $request->get('sort');

        // If the sort parameter is not set, return the query with default sorting
        if (!$sort) {
            return $query->orderBy('id', 'asc');
        }

        if ($sort) {
            // Get the order column from the request
            $orderSettings = $request->get('sort', 'name');
            // If the order column is not set, return an error response
            $orderColumnName = str_starts_with($orderSettings, '-') ? substr($orderSettings, 1) : $orderSettings;
            // If the order column is not set, return an error response
            $orderDirection = str_starts_with($orderSettings, '-') ? 'desc' : 'asc';
            // If the column is not allowed, return an error response
            if (!empty($allowedColumns) && !in_array($orderColumnName, $allowedColumns)) {
                return $this->errorResponse('Invalid order column: ' . $orderColumnName, ['sort' => 'INVALID_ORDER_COLUMN'], 400);
            }
            // If the column is allowed, sort the query
            if (empty($allowedColumns) || in_array($orderColumnName, $allowedColumns)) {
                return $query->orderBy($orderColumnName, $orderDirection);
            }
        }

        return $query;
    }
}
