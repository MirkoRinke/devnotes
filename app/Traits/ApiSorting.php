<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait ApiSorting {

    /**
     * Sort the query results based on the request
     *
     * @param Request $request
     * @param Builder $query
     * @param array $allowedColumns
     * @return Builder
     */
    public function sort(Request $request, Builder $query, $allowedColumns = []): Builder {
        if ($request->query('sort') !== null) {
            $orderSettings = $request->input('sort', 'name');

            $orderColumnName = str_starts_with($orderSettings, '-') ? substr($orderSettings, 1) : $orderSettings;
            $orderDirection = str_starts_with($orderSettings, '-') ? 'desc' : 'asc';

            if (empty($allowedColumns) || in_array($orderColumnName, $allowedColumns)) {
                return $query->orderBy($orderColumnName, $orderDirection);
            }
        }
        return $query;
    }    
    
}
