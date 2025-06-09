<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Traits\ApiResponses;

/**
 * This ApiFiltering Trait provides a method to filter a query based on the request parameters.
 * It checks if the filter parameters are valid and applies them to the query.
 * Supports advanced filtering with operators for direct fields and relations.
 * Relation filters require the dot notation (relation.field) format.
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
     * @example | // Filter by specific field in relation: filter[tags.name]=PHP
     * @example | // Filter with operators: filter[created_at]=gte:2023-01-01
     * @example | // Special operators: filter[deleted_at]=is:null (find only records where deleted_at is NULL)
     * @example | // Multiple values: filter[tags.name]=PHP,JavaScript (finds records matching ANY value)
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

                [$key, $filterArray, $targetField] = $this->extractRelationField($key, $filterArray);
                [$filterArray[$key], $operators] = $this->extractOperators($filterArray[$key]);

                if (!in_array($key, $allowedFilterColumns) && !in_array($key, $relationKeys)) {
                    return $this->errorResponse('Invalid filter column: ' . $key, ['filter' => 'INVALID_FILTER_COLUMN'], 400);
                }


                /**
                 * Check if the filter value is an array
                 * If it is, ensure all values are properly formatted
                 */
                foreach ($filterArray[$key] as $value) {
                    if (!preg_match('/^[a-zA-Z0-9_,\.\-:@ ]+$/', $value)) { // allow alphanumeric, underscores, commas, periods, colons, dashes, at-signs (@) and spaces
                        return $this->errorResponse('Invalid filter value format', 'INVALID_VALUE_FORMAT', 400);
                    }
                }

                /**
                 * Check if the filter column is a valid format
                 * The filter column must be alphanumeric and can contain underscores
                 */
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) { // allow alphanumeric and underscores only
                    return $this->errorResponse('Invalid column format', 'INVALID_FORMAT', 400);
                }

                /**
                 * If the filter column is a relation key, process it separately
                 */
                if (in_array($key, $relationKeys)) {
                    $value = $filterArray[$key];
                    $allowedColumns = $relationFilters[$key];

                    if ($targetField === null) {
                        return $this->errorResponse('Target field is required for relation: ' . $key, ['filter' => 'MISSING_TARGET_FIELD'], 400);
                    }

                    if (!in_array($targetField, $allowedColumns)) {
                        return $this->errorResponse('Invalid target field: ' . $targetField . ' for relation: ' . $key, ['filter' => 'INVALID_TARGET_FIELD'], 400);
                    }

                    $query = $this->filterRelations($query, $key, $targetField, $value, $operators);
                    unset($filterArray[$key]);
                }
            }


            /**
             * Process remaining non-relation filters
             */
            $query->where(function ($queryBuilder) use ($filterArray, $operators) {
                foreach ($filterArray as $key => $values) {

                    // Group conditions for each column to maintain proper SQL structure
                    $queryBuilder->where(function ($valueGroupQuery) use ($key, $values, $operators) {

                        // For each filter value, add appropriate condition with OR logic
                        $this->handleOperators($valueGroupQuery, $key, $values, $operators);
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
     * It supports comma-separated values for OR filtering in the specified target field.
     * The search behavior depends on the operator used (default: contains).
     * Supports advanced filtering with multiple operators.
     * 
     * @param Builder $query The query builder to apply the relation filter to
     * @param string $relation The name of the relation to filter on (e.g., 'tags', 'language', 'user')
     * @param string $targetField The field in the related model to filter on
     * @param mixed $values Single value or array/comma-separated list of values to filter by
     * @param array $operators Array of operators to apply to each value
     * 
     * @return Builder The query with relation filters applied
     * 
     * @example | $this->filterRelations($query, $relation, $targetField, $values, $operators);
     */
    protected function filterRelations(Builder $query, string $relation, string $targetField, mixed $values, array $operators): Builder {

        // Use whereHas to filter by related models that match the condition
        $query->whereHas($relation, function ($relationBuilder) use ($targetField, $values, $operators) {

            // Group all conditions for this relation to maintain proper SQL parentheses
            $relationBuilder->where(function ($valueGroupQuery) use ($targetField, $values, $operators) {

                // For each filter value, create a separate OR condition
                $this->handleOperators($valueGroupQuery, $targetField, $values, $operators);
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
     * 
     * @example | $this->extractRelationField($key, $filterArray);
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
     * - eq: Exact match (=)
     * - starts: Starts with (LIKE 'value%')
     * - ends: Ends with (LIKE '%value')
     * - gt: Greater than (>)
     * - lt: Less than (<)
     * - gte: Greater than or equal to (>=)
     * - lte: Less than or equal to (<=)
     * - is: Special operator for NULL checks (is:null, is:not_null)
     * - between: Range search (between:1,10)
     *
     * @param mixed $values The filter values (string or array)
     * @return array [values, operators]
     * 
     * @example | $this->extractOperators($values);
     */
    protected function extractOperators($values): array {
        $operators = [];
        $allowedOperators = [
            'eq', // Exact match
            'contains', // Default case-insensitive partial match
            'starts', // Starts with
            'ends', // Ends with
            'gt', // Greater than
            'lt', // Less than
            'gte', // Greater than or equal to
            'lte', // Less than or equal to
            'is', // Special operator for NULL checks
            'between' // Range search (e.g., between:1,10)
        ];

        if (!is_array($values)) {
            // Regex for bracket notation filters: Captures expressions like "between:[1,10]" or "in:[a,b,c]"
            // ([a-z]+)    - Captures the operator name (e.g., "between")
            // :\[         - Matches the colon and opening bracket
            // ([^\]]+)    - Captures everything inside brackets until closing bracket
            // \]          - Matches the closing bracket
            $values = preg_replace_callback('/([a-z]+):\[([^\]]+)\]/', function ($matches) {
                $operator = $matches[1];
                $innerValues = str_replace(',', '|||', $matches[2]);
                return "{$operator}:{$innerValues}";
            }, $values);
            $values = explode(',', $values);
        }

        foreach ($values as $index => $value) {
            if (strpos($value, ':') !== false) {
                list($possibleOperator, $explodeValue) = explode(':', $value, 2);

                // Check if the operator is allowed
                if (in_array($possibleOperator, $allowedOperators)) {
                    $explodeValue = str_replace('|||', ',', $explodeValue); // Replace custom delimiter back to comma
                    $values[$index] = $explodeValue;
                    $operators[$index] = $possibleOperator;
                } else { // For unrecognized operators, preserve the original value and apply the default 'contains' operator
                    $values[$index] = $value;
                    $operators[$index] = 'contains'; // Default operator
                }
            } else {
                $values[$index] = $value;
                $operators[$index] = 'contains'; // Default operator
            }
        }

        return [$values, $operators];
    }


    /**
     * Applies filter operators to a query
     * 
     * This method processes values with their corresponding operators and builds
     * the appropriate query conditions. All conditions are combined with OR logic.
     * 
     * @param Builder $query The query builder instance
     * @param string $key The field name to filter on
     * @param array $values Array of values to filter by
     * @param array $operators Array of operators corresponding to each value
     * @return void
     * 
     * @example | $this->handleOperators($query, $key, $values, $operators);
     */
    protected function handleOperators($query, $key, $values, $operators) {
        foreach ($values as $index => $value) {
            $trimmedValue = trim($value);
            $operator = $operators[$index];

            switch ($operator) {
                case 'eq': // Exact match
                    $query->orWhere($key, '=', $trimmedValue);
                    break;
                case 'starts': // Starts with
                    $query->orWhereRaw("LOWER({$key}) LIKE LOWER(?)", [$trimmedValue . '%']);
                    break;
                case 'ends': // Ends with
                    $query->orWhereRaw("LOWER({$key}) LIKE LOWER(?)", ['%' . $trimmedValue]);
                    break;
                case 'gt': // Greater than
                    $query->orWhere($key, '>', $trimmedValue);
                    break;
                case 'lt': // Less than
                    $query->orWhere($key, '<', $trimmedValue);
                    break;
                case 'gte': // Greater than or equal to
                    $query->orWhere($key, '>=', $trimmedValue);
                    break;
                case 'lte': // Less than or equal to
                    $query->orWhere($key, '<=', $trimmedValue);
                    break;
                case 'between': // Range search
                    if (strpos($trimmedValue, ',') !== false) {
                        list($min, $max) = explode(',', $trimmedValue, 2);
                        $query->orWhereBetween($key, [trim($min), trim($max)]);
                    }
                    break;
                case 'is': // Special operator for NULL checks
                    if ($trimmedValue === 'null') {
                        $query->orWhereNull($key);
                    } else if ($trimmedValue === 'not_null') {
                        $query->orWhereNotNull($key);
                    }
                    break;
                case 'contains': // Default case-insensitive partial match
                default:
                    $query->orWhereRaw("LOWER({$key}) LIKE LOWER(?)", ['%' . $trimmedValue . '%']);
                    break;
            }
        }
    }
}
