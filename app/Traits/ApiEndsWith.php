<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Traits\ApiResponses;


trait ApiEndsWith {

    use ApiResponses;

    /**
     * Filter query results based on prefix matching where column values end with given string
     * 
     * Accepts request parameters in the format: endsWith[column]=value
     * Example: ?endsWith[name]=jo - Returns records where name ends with "jo"
     * 
     * @param Request $request The request object containing the endsWith parameters
     * @param Builder $query The query builder instance to be modified
     * @param array $allowedColumns List of column names that are allowed to be filtered
     * 
     * @return Builder|JsonResponse Returns the modified query builder if successful,
     *                             or a JSON error response if validation fails
     */
    public function endsWith(Request $request, Builder $query, $allowedColumns = []) {
        if ($request->has('endsWith')) {
            $endsWithFilters = $request->endsWith;

            // Check if endsWith is an array
            if (!is_array($endsWithFilters)) {
                return $this->errorResponse('endsWith parameter must be an array format like endsWith[column]=value', 'INVALID_ENDSWITH_FORMAT', 400);
            }

            foreach ($endsWithFilters as $column => $value) {
                // Check if the column is not empty
                if (empty($value)) {
                    return $this->errorResponse("Empty value provided for endsWith[{$column}]. Please provide a non-empty value.", 'EMPTY_ENDSWITH_VALUE', 400);
                }

                // Check if the column is in the allowed columns list
                if (!in_array($column, $allowedColumns)) {
                    return $this->errorResponse("Filtering by '{$column}' is not allowed", 'INVALID_FILTER_COLUMN', 400);
                }

                $query->where($column, 'LIKE', '%' . $value);
            }
        }
        return $query;
    }
}
