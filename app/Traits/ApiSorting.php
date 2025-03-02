<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

use Illuminate\Http\JsonResponse;

use App\Traits\ApiResponses;

trait ApiSorting {

    use ApiResponses;

    /**
     * Sort the query results based on the request
     *
     * @param Request $request
     * @param Builder $query
     * @param array $allowedColumns
     * @return Builder
     */
    public function sort(Request $request, Builder $query, $allowedColumns = []): JsonResponse|Builder {
        if ($request->query('sort') !== null) {
            $orderSettings = $request->input('sort', 'name');

            $orderColumnName = str_starts_with($orderSettings, '-') ? substr($orderSettings, 1) : $orderSettings;
            $orderDirection = str_starts_with($orderSettings, '-') ? 'desc' : 'asc';

            // Check if the column is allowed to be sorted. If not, return an error response
            if (!empty($allowedColumns) && !in_array($orderColumnName, $allowedColumns)) {
                return $this->errorResponse('Invalid order column: ' . $orderColumnName, ['sort' => 'INVALID_ORDER_COLUMN'], 400);             
            }

            // If the column is allowed, sort the query results based on the column and direction
            if (empty($allowedColumns) || in_array($orderColumnName, $allowedColumns)) {
                return $query->orderBy($orderColumnName, $orderDirection);
            }
        }        
        return $query;
    }    
    
}
