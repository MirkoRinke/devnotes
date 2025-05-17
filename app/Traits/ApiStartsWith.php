<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Traits\ApiResponses;

/**
 * This ApiStartsWith Trait provides a method to filter a query based on the request parameters.
 * It checks if the startsWith parameters are valid and applies them to the query.
 */
trait ApiStartsWith {

    /**
     *  The traits used in the Trait
     */
    use ApiResponses;

    /**
     * Filter query results based on prefix matching where column values start with given string
     * 
     * Accepts request parameters in the format: startsWith[column]=value
     * Example: ?startsWith[name]=jo - Returns records where name starts with "jo"
     * 
     * @param Request $request The request object containing the startsWith parameters
     * @param Builder $query The query builder instance to be modified
     * @param array $allowedColumns List of column names that are allowed to be filtered
     * 
     * @return Builder|JsonResponse Returns the modified query builder if successful,
     *                             or a JSON error response if validation fails
     * 
     * @example | $this->startsWith($request, $query, (array) $config);
     */
    public function startsWith(Request $request, Builder $query, array $allowedColumns = []): JsonResponse|Builder {
        if ($request->has('startsWith')) {
            $startsWithFilters = $request->startsWith;

            // Check if startsWith is an array
            if (!is_array($startsWithFilters)) {
                return $this->errorResponse('startsWith parameter must be an array format like startsWith[column]=value', 'INVALID_STARTSWITH_FORMAT', 400);
            }

            foreach ($startsWithFilters as $column => $value) {
                // Check if the column is not empty
                if (empty($value)) {
                    return $this->errorResponse("Empty value provided for startsWith[{$column}]. Please provide a non-empty value.", 'EMPTY_STARTSWITH_VALUE', 400);
                }

                // Check if the column is in the allowed columns list
                if (!in_array($column, $allowedColumns)) {
                    return $this->errorResponse("Filtering by '{$column}' is not allowed", 'INVALID_FILTER_COLUMN', 400);
                }

                $query->where($column, 'LIKE', $value . '%');
            }
        }
        return $query;
    }
}
