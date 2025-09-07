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
     * Sort the query based on the request parameters.
     * The 'sort' parameter can be a comma-separated string or an array.
     * Prefix a column with '-' for descending order, otherwise ascending is assumed.
     * If 'random' is specified, the results will be returned in random order.
     *
     * @param Request $request
     * @param Builder $query
     * @param array $allowedColumns
     * @return Builder
     * 
     * @example | $this->sort($request, $query, (array)$config);
     */
    protected function sort(Request $request, Builder $query, array $allowedColumns = []): JsonResponse|Builder {
        $sort = $request->get('sort');

        if (!$sort) {
            return $query->orderBy('id', 'asc');
        }

        if (is_string($sort)) {
            $sort = explode(',', $sort);
        }

        foreach ($sort as $orderSettings) {

            if ($orderSettings === 'random') {
                $query->inRandomOrder();
                break;
            }

            $orderColumnName = str_starts_with($orderSettings, '-') ? substr($orderSettings, 1) : $orderSettings;
            $orderDirection = str_starts_with($orderSettings, '-') ? 'desc' : 'asc';

            if (!empty($allowedColumns) && !in_array($orderColumnName, $allowedColumns)) {
                return $this->errorResponse('Invalid order column: ' . $orderColumnName, ['sort' => 'INVALID_ORDER_COLUMN'], 400);
            }

            $query->orderBy($orderColumnName, $orderDirection);
        }

        return $query;
    }
}
