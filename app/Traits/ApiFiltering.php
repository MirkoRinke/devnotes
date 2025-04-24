<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Traits\ApiResponses;

use function PHPUnit\Framework\isEmpty;

trait ApiFiltering {

    use ApiResponses;

    /**
     * Filter the query based on the request
     * 
     * @param Request $request
     * @param Builder $query
     * @param array $allowedFilterColumns
     * 
     * @return JsonResponse|Builder
     */
    public function filter(Request $request, Builder $query, $allowedFilterColumns = []): JsonResponse|Builder {
        // Get the filter array from the request
        $filterArray = $request->query('filter');

        if ($filterArray) {

            // Check if filterArray is actually an array
            if (!is_array($filterArray)) {
                return $this->errorResponse('Filter parameter must use array format like filter[column]=value', 'INVALID_FILTER_FORMAT', 400);
            }


            // Check if the filter column is allowed
            foreach (array_keys($filterArray) as $key) {
                // If the value is empty, return an error response
                if (empty($filterArray[$key])) {
                    return $this->errorResponse('Empty value provided for filter[' . $key . ']. Please provide a non-empty value.', 'EMPTY_FILTER_VALUE', 400);
                }
                // Check if the filter column is in the allowed columns list
                if (!in_array($key, $allowedFilterColumns)) {
                    return $this->errorResponse('Invalid filter column: ' . $key, ['filter' => 'INVALID_FILTER_COLUMN'], 400);
                }
            }

            // Group all filters in a single AND clause to maintain security constraints
            $query->where(function ($queryBuilder) use ($filterArray) {
                // For each filter column, add a where clause to the query
                foreach ($filterArray as $key => $values) {

                    // If the value is not an array, convert it to an array
                    if (!is_array($values)) {
                        $values = explode(',', $values);
                    }


                    // Create a subquery for each filter column
                    $queryBuilder->where(function ($subQuery) use ($key, $values) {
                        // Add a where clause to the query for each value in the array
                        foreach ($values as $value) {
                            if ($value === 'is:null') {
                                $subQuery->orWhereNull($key);
                            } else if ($value === 'is:not_null') {
                                $subQuery->orWhereNotNull($key);
                            } else {
                                $subQuery->orWhere($key, 'LIKE', '%' . $value . '%');
                            }
                        }
                    });
                }
            });
        }

        return $query;
    }
}
