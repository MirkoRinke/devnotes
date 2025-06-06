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
     * Supports dot notation (relation.field) to filter by specific fields in related models.
     * 
     * @param Request $request The HTTP request containing filter parameters
     * @param Builder $query The query builder to apply filters to
     * @param array $allowedFilterColumns Columns that can be filtered directly (e.g., 'title', 'description')
     * @param array $relationFilters Associative array of relations and their filterable columns (e.g., 'tags' => ['name'], 'language' => ['name'])
     * 
     * @return JsonResponse|Builder Returns error response on invalid filters, or the filtered query
     * 
     * @example | $this->filter($request, $query, $allowedFilterColumns, $relationFilters);
     * 
     * @example | // Filter by post title: filter[title]=My Post Title
     * @example | // Filter by relation: filter[tags]=PHP
     * @example | // Filter by specific field in relation: filter[tags.name]=PHP
     */
    public function filter(Request $request, Builder $query, array $allowedFilterColumns = [], array $relationFilters = []): JsonResponse|Builder {

        $filterArray = $request->query('filter');

        if ($filterArray) {

            $relationKeys = []; // Initialize an empty array to hold relation keys
            if (!empty($relationFilters) && is_array($relationFilters)) {
                $relationKeys = array_keys($relationFilters); // Get the relation keys (e.g., 'tags', 'language', 'technology', 'user')
            }

            if (!is_array($filterArray)) {
                return $this->errorResponse('Filter parameter must use array format like filter[column]=value', 'INVALID_FILTER_FORMAT', 400);
            }

            foreach (array_keys($filterArray) as $key) {

                // Check the key has a dot notation (relation.field)
                if (strpos($key, '.') !== false) {
                    list($relation, $field) = explode('.', $key, 2);
                    $value = $filterArray[$key];
                    unset($filterArray[$key]);

                    $filterArray[$relation] = $value;

                    $key = $relation;
                    $targetField = $field;
                }

                if (empty($filterArray[$key])) {
                    return $this->errorResponse('Empty value provided for filter[' . $key . ']. Please provide a non-empty value.', 'EMPTY_FILTER_VALUE', 400);
                }

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

                /**
                 * If the filter column is a relation key, process it separately
                 */
                if (in_array($key, $relationKeys)) {
                    $value = $filterArray[$key];
                    $allowedColumns = $relationFilters[$key];
                    $targetField = $targetField ?? null;

                    $query = $this->filterRelations($query, $key, $value, $allowedColumns, $targetField);
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

                            if ($trimmedValue === 'is:null') {
                                $valueGroupQuery->orWhereNull($key);
                            } else if ($trimmedValue === 'is:not_null') {
                                $valueGroupQuery->orWhereNotNull($key);
                            } else {
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
     * If a target field is specified, only that field will be searched instead of all allowed columns.
     * 
     * @param Builder $query The query builder to apply the relation filter to
     * @param string $relation The name of the relation to filter on (e.g., 'tags', 'language', 'user')
     * @param mixed $values Single value or array/comma-separated list of values to filter by (e.g., 'PHP', 'JavaScript,Python')
     * @param array $allowedColumns The columns in the related model that can be filtered (e.g., ['name', 'description'])
     * @param string|null $targetField Optional specific field to search in, if provided will only search that field
     * 
     * @return Builder The query with relation filters applied
     * 
     * @example | $this->filterRelations($query, $relation, $values, $allowedColumns, $targetField);
     */
    private function filterRelations(Builder $query, string $relation, mixed $values, array $allowedColumns, ?string $targetField): Builder {

        // Use whereHas to filter by related models that match the condition
        $query->whereHas($relation, function ($relationBuilder) use ($values, $allowedColumns, $targetField) {

            // Group all conditions for this relation to maintain proper SQL parentheses
            $relationBuilder->where(function ($valueGroupQuery) use ($values, $allowedColumns, $targetField) {

                if (!is_array($values)) {
                    $values = explode(',', $values);
                }

                // For each filter value, create a separate OR condition
                foreach ($values as $value) {
                    $trimmedValue = trim($value);

                    // Group conditions for each value to ensure proper precedence
                    $valueGroupQuery->orWhere(function ($fieldQuery) use ($allowedColumns, $trimmedValue, $targetField) {

                        // If a target field is specified, search for the value in that specific field
                        if ($targetField !== null && in_array($targetField, $allowedColumns)) {
                            if ($trimmedValue === 'is:null') {
                                $fieldQuery->whereNull($targetField);
                            } else if ($trimmedValue === 'is:not_null') {
                                $fieldQuery->whereNotNull($targetField);
                            } else {
                                $fieldQuery->whereRaw('LOWER(' . $targetField . ') LIKE LOWER(?)', ['%' . $trimmedValue . '%']);
                            }
                        } else {
                            // For each allowed column, search for the value with case-insensitive partial matching
                            foreach ($allowedColumns as $column) {
                                $fieldQuery->orWhereRaw('LOWER(' . $column . ') LIKE LOWER(?)', ['%' . $trimmedValue . '%']);
                            }
                        }
                    });
                }
            });
        });

        return $query;
    }
}
