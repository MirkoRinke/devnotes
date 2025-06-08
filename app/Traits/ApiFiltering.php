<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Traits\ApiResponses;

/**
 * This ApiFiltering Trait provides a method to filter a query based on the request parameters.
 * It checks if the filter parameters are valid and applies them to the query.
 * Supports advanced filtering with operators ( In progress ) and relation filtering.
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
     * @example | // Special operators: filter[deleted_at]=is:null (find only records where deleted_at is NULL)
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

                if (empty($filterArray[$key])) {
                    return $this->errorResponse('Empty value provided for filter[' . $key . ']. Please provide a non-empty value.', 'EMPTY_FILTER_VALUE', 400);
                }

                // Check the key has a dot notation (relation.field)
                [$key, $filterArray, $targetField] = $this->extractRelationField($key, $filterArray);

                // Check if the key has a colon notation (operator:value) 
                // TODO Use this to support operators like 'contains', 'equals', etc. 
                [$filterArray[$key], $operators] = $this->extractOperators($filterArray[$key]);


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

                    if (!in_array($targetField, $allowedColumns)) {
                        return $this->errorResponse('Invalid target field: ' . $targetField . ' for relation: ' . $key, ['filter' => 'INVALID_TARGET_FIELD'], 400);
                    }

                    $query = $this->filterRelations($query, $key, $value, $allowedColumns, $targetField, $operators);
                    unset($filterArray[$key]);
                }
            }


            // Process remaining non-relation filters
            $query->where(function ($queryBuilder) use ($filterArray, $operators) {

                // Process each filter column separately
                foreach ($filterArray as $key => $values) {

                    if (!is_array($values)) {
                        $values = explode(',', $values);
                    }

                    // Group conditions for each column to maintain proper SQL structure
                    $queryBuilder->where(function ($valueGroupQuery) use ($key, $values, $operators) {

                        // For each filter value, add appropriate condition with OR logic
                        foreach ($values as $index => $value) {
                            $trimmedValue = trim($value);
                            $operator = $operators[$index]; //TODO Use operator to modify the query             

                            if ($operator === 'is') {
                                if ($trimmedValue === 'null') {
                                    $valueGroupQuery->orWhereNull($key);
                                } else if ($trimmedValue === 'not_null') {
                                    $valueGroupQuery->orWhereNotNull($key);
                                }
                            } else if (is_numeric($trimmedValue)) {
                                $valueGroupQuery->orWhere($key, '=', $trimmedValue);
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
     * Supports advanced filtering with operators ( In progress ).
     * 
     * @param Builder $query The query builder to apply the relation filter to
     * @param string $relation The name of the relation to filter on (e.g., 'tags', 'language', 'user')
     * @param mixed $values Single value or array/comma-separated list of values to filter by (e.g., 'PHP', 'JavaScript,Python')
     * @param array $allowedColumns The columns in the related model that can be filtered (e.g., ['name', 'description'])
     * @param string|null $targetField Optional specific field to search in, if provided will only search that field
     * 
     * @return Builder The query with relation filters applied
     * 
     * @example | $this->filterRelations($query, string $relation, mixed $values, array $allowedColumns, ?string $targetField = null);
     */
    protected function filterRelations(Builder $query, string $relation, mixed $values, array $allowedColumns, ?string $targetField, array $operators): Builder {

        // Use whereHas to filter by related models that match the condition
        $query->whereHas($relation, function ($relationBuilder) use ($values, $allowedColumns, $targetField, $operators) {

            // Group all conditions for this relation to maintain proper SQL parentheses
            $relationBuilder->where(function ($valueGroupQuery) use ($values, $allowedColumns, $targetField, $operators) {

                if (!is_array($values)) {
                    $values = explode(',', $values);
                }

                // For each filter value, create a separate OR condition
                foreach ($values as $index => $value) {
                    $trimmedValue = trim($value);
                    $operator = $operators[$index];

                    // Group conditions for each value to ensure proper precedence
                    $valueGroupQuery->orWhere(function ($fieldQuery) use ($allowedColumns, $trimmedValue, $targetField, $operator) {

                        // If a target field is specified, search for the value in that specific field
                        //Todo Use the operator to modify the query
                        if ($targetField !== null) {
                            if ($operator === 'is') {
                                if ($trimmedValue === 'null') {
                                    $fieldQuery->whereNull($targetField);
                                } else if ($trimmedValue === 'not_null') {
                                    $fieldQuery->whereNotNull($targetField);
                                }
                            } else if (is_numeric($trimmedValue)) {
                                $fieldQuery->where($targetField, '=', $trimmedValue);
                            } else {
                                $fieldQuery->whereRaw('LOWER(' . $targetField . ') LIKE LOWER(?)', ['%' . $trimmedValue . '%']);
                            }
                        } else {
                            // For each allowed column, search for the value with case-insensitive partial matching
                            foreach ($allowedColumns as $column) {
                                if (is_numeric($trimmedValue)) {
                                    $fieldQuery->orWhere($column, '=', $trimmedValue);
                                } else {
                                    $fieldQuery->orWhereRaw('LOWER(' . $column . ') LIKE LOWER(?)', ['%' . $trimmedValue . '%']);
                                }
                            }
                        }
                    });
                }
            });
        });

        return $query;
    }

    /**
     * Extract relation and target field from filter key with dot notation
     *
     * @param string $key The filter key (possibly with dot notation)
     * @param array $filterArray The current filter array
     * @return array [key, filterArray, ?string targetField]
     */
    protected function extractRelationField(string $key, array $filterArray): array {
        $targetField = null;

        if (strpos($key, '.') !== false) {
            list($relation, $targetField) = explode('.', $key, 2);
            $value = $filterArray[$key];
            unset($filterArray[$key]);

            $filterArray[$relation] = $value;
            $key = $relation;
        }

        return [$key, $filterArray, $targetField];
    }


    /**
     * Extract operators from filter values with colon notation
     *
     * Supported operators:
     * - contains: (default) Case-insensitive partial match (%value%)
     * - is: Special operator for NULL checks (is:null, is:not_null)
     *
     * @param mixed $values The filter values (string or array)
     * @return array [values, operators]
     */
    protected function extractOperators($values): array {
        $operators = [];

        if (!is_array($values)) {
            $values = explode(',', $values);
        }

        foreach ($values as $index => $value) {
            if (strpos($value, ':') !== false) {
                list($operator, $explodeValue) = explode(':', $value, 2);
                $values[$index] = $explodeValue;
                $operators[$index] = $operator;
            } else {
                $values[$index] = $value;
                $operators[$index] = 'contains'; // Default operator
            }
        }

        return [$values, $operators];
    }
}
