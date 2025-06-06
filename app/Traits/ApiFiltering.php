<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Traits\ApiResponses;

/**
 * This ApiFiltering Trait provides a method to filter a query based on the request parameters.
 * It checks if the filter parameters are valid and applies them to the query.
 */
trait ApiFiltering {

    /**
     *  The traits used in the Trait
     */
    use ApiResponses;


    /**
     * Applies filters to a query based on request parameters.
     * 
     * This method processes both direct column filters and relation filters from the request.
     * It validates that all filter columns are allowed and properly formatted before applying them.
     * Special operators like 'is:null' and 'is:not_null' are supported for direct column filters.
     * 
     * @param Request $request The HTTP request containing filter parameters
     * @param Builder $query The query builder to apply filters to
     * @param array $allowedFilterColumns Columns that can be filtered directly (e.g., 'title', 'description')
     * @param array $relationFilters Associative array of relations and their filterable columns (e.g., 'tags' => ['name'], 'language' => ['name'])
     * 
     * @return JsonResponse|Builder Returns error response on invalid filters, or the filtered query
     * 
     * @example | $this->filter($request, $query, $allowedFilterColumns, $relationFilters);
     */
    public function filter(Request $request, Builder $query, array $allowedFilterColumns = [], array $relationFilters = []): JsonResponse|Builder {

        $relationKeys = []; // Initialize an empty array to hold relation keys
        if (!empty($relationFilters) && is_array($relationFilters)) {
            $relationKeys = array_keys($relationFilters); // Get the relation keys (e.g., 'tags', 'language', 'technology', 'user')
        }

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

                // Check if the filter column is in the allowed columns list or in the relation filters
                if (!in_array($key, $allowedFilterColumns) && !in_array($key, $relationKeys)) {
                    return $this->errorResponse('Invalid filter column: ' . $key, ['filter' => 'INVALID_FILTER_COLUMN'], 400);
                }

                /**
                 * Check if the filter column is a valid format
                 * The filter column must be alphanumeric and can contain underscores
                 */
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                    return $this->errorResponse('Invalid column format', 'INVALID_FORMAT', 400);
                }

                // If the filter column is a relation key, process it separately
                if (in_array($key, $relationKeys)) {
                    $value = $filterArray[$key];
                    $allowedColumns = $relationFilters[$key];

                    $query = $this->filterRelations($query, $key, $value, $allowedColumns);
                    unset($filterArray[$key]);
                }
            }


            // Process remaining non-relation filters
            $query->where(function ($queryBuilder) use ($filterArray) {

                // Process each filter column separately
                foreach ($filterArray as $key => $values) {

                    if (!is_array($values)) {
                        $values = explode(',', $values);
                    }

                    // Group conditions for each column to maintain proper SQL structure
                    $queryBuilder->where(function ($valueGroupQuery) use ($key, $values) {

                        // For each filter value, add appropriate condition with OR logic
                        foreach ($values as $value) {
                            $trimmedValue = trim($value);

                            // Handle special filter operators
                            if ($trimmedValue === 'is:null') {
                                // Filter for NULL values
                                $valueGroupQuery->orWhereNull($key);
                            } else if ($trimmedValue === 'is:not_null') {
                                // Filter for non-NULL values
                                $valueGroupQuery->orWhereNotNull($key);
                            } else {
                                // Default: case-insensitive partial matching
                                $valueGroupQuery->orWhereRaw('LOWER(' . $key . ') LIKE LOWER(?)', ['%' . $trimmedValue . '%']);
                            }
                        }
                    });
                }
            });
        }

        return $query;
    }

    /**
     * Filters a query based on related model values.
     * 
     * This method creates a whereHas clause to filter the main query based on values in related models.
     * It supports comma-separated values for OR filtering and searches across multiple allowed columns.
     * The search is case-insensitive and supports partial matching.
     * 
     * @param Builder $query The query builder to apply the relation filter to
     * @param string $relation The name of the relation to filter on (e.g., 'tags', 'language', 'technology', 'user')
     * @param mixed $values Single value or array/comma-separated list of values to filter by (e.g., 'PHP', 'JavaScript,Python')
     * @param array $allowedColumns The columns in the related model that can be filtered (e.g., ['name', 'description'])
     * 
     * @return Builder The query with relation filters applied
     * 
     * @example | $this->filterRelations($query, $relation, $values, $allowedColumns);
     */
    private function filterRelations(Builder $query, string $relation, mixed $values, array $allowedColumns): Builder {

        // Use whereHas to filter by related models that match the condition
        $query->whereHas($relation, function ($relationBuilder) use ($values, $allowedColumns) {

            // Group all conditions for this relation to maintain proper SQL parentheses
            $relationBuilder->where(function ($valueGroupQuery) use ($values, $allowedColumns) {

                if (!is_array($values)) {
                    $values = explode(',', $values);
                }

                // For each filter value, create a separate OR condition
                foreach ($values as $value) {
                    $trimmedValue = trim($value);

                    // Group conditions for each value to ensure proper precedence
                    $valueGroupQuery->orWhere(function ($fieldQuery) use ($allowedColumns, $trimmedValue) {

                        // For each allowed column, search for the value with case-insensitive partial matching
                        foreach ($allowedColumns as $column) {
                            $fieldQuery->orWhereRaw('LOWER(' . $column . ') LIKE LOWER(?)', ['%' . $trimmedValue . '%']);
                        }
                    });
                }
            });
        });

        return $query;
    }
}
