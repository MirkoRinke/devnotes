<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Traits\ApiResponses;
use App\Traits\CacheHelper;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

/**
 * This ApiSelectable Trait provides a method to select specific columns from a query
 * based on the request parameters. It checks if the select parameters are valid and applies them to the query.
 */
trait ApiSelectable {

    /**
     *  The traits used in the Trait
     */
    use ApiResponses, CacheHelper;

    /**
     * Select the columns to return in the response
     *
     * @param Request $request
     * @param Builder $query
     * @param array $allowedAttributes
     * @return JsonResponse|Builder
     * 
     * @example | $this->select($request, $query, (array) $config);
     */
    public function select(Request $request, Builder $query, array $allowedAttributes = []): JsonResponse|Builder {
        $select = $request->query('select');

        if ($select) {
            if (is_string($select)) {
                $select = explode(',', $select);
            }

            /**
             * Check if the select parameter is a count select
             * This is done by checking if the select parameter contains any 'count:' prefix.
             * If it does, it will return a JsonResponse with the count of those columns.
             */
            $isCountSelect = $this->countSelect($request, $query, $select);
            if ($isCountSelect) {
                return $isCountSelect;
            }

            /**
             * Check if the select parameter is a sum select
             * This is done by checking if the select parameter contains any 'sum:' prefix.
             * If it does, it will return a JsonResponse with the sum of those columns.
             */
            $isSumSelect = $this->sumSelect($query, $select);
            if ($isSumSelect) {
                return $isSumSelect;
            }

            $validAttributes = array_intersect($select, $allowedAttributes);
            $invalidAttributes = array_diff($select, $allowedAttributes);

            if (empty($validAttributes) || !empty($invalidAttributes)) {
                $invalidAttributesString = implode(', ', $invalidAttributes);
                return $this->errorResponse("Invalid select column: $invalidAttributesString", ['select' => 'INVALID_SELECT_COLUMN'], 400);
            }

            if (in_array('id', $allowedAttributes) && !in_array('id', $validAttributes)) {
                array_unshift($validAttributes, 'id');
            }

            return $query->select($validAttributes);
        }
        return $query;
    }

    /**
     * Process count select operations from the query
     *
     * This method extracts columns with 'count:' prefix from the select parameter
     * and returns a count of records based on those columns. It supports:
     * - Basic counting of a single column across all records
     * - Delegating to filtered counting when filter parameters are present
     *
     * @param Request $request The HTTP request containing filter parameters
     * @param Builder $query The query builder instance
     * @param array $select The select parameters from the request
     * @return JsonResponse|null Returns a JsonResponse with count results or null if no count columns are found
     * 
     * @example | $this->countSelect($request, $query, $select);
     * 
     * Example URL: /select=count:post_type
     * 
     * {
     *   "status": "success",
     *   "message": "Count retrieved successfully",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *       "post_type": 500
     *   }
     * }
     */
    private function countSelect(Request $request, $query, $select): JsonResponse|null {
        $modelClass = get_class($query->getModel());
        $modelSchema = Schema::getColumnListing((new $modelClass())->getTable());

        $countSelect = [];

        foreach ($select as $value) {
            if (str_starts_with($value, 'count:')) {
                $countSelect[] = str_replace('count:', '', $value);
            }
        }

        if (count($countSelect) > 1) {
            return $this->errorResponse('Multiple counts are not supported.', ['select' => 'MULTIPLE_COUNTS_NOT_SUPPORTED'], 400);
        }

        if (!in_array($countSelect[0], $modelSchema)) {
            return $this->errorResponse("Invalid count column: {$countSelect[0]}", 'INVALID_COUNT_COLUMN', 400);
        }

        if ($request->has('filter') && !empty($countSelect)) {
            return $this->countSelectGroupedByFilter($request, $query, $countSelect, $modelSchema);
        }

        if (!empty($countSelect)) {
            $column = $countSelect[0];
            $count = $query->count($column);
            return $this->successResponse([$column => $count], 'Count retrieved successfully');
        }

        return null;
    }


    /**
     * Count records grouped by filter values
     *
     * This specialized counting method groups records by filter values and returns counts for each value.
     *
     * @param Request $request The HTTP request containing filter parameters
     * @param Builder $query The query builder instance
     * @param array $countSelect The processed count parameters (without 'count:' prefix)
     * @return JsonResponse|null Returns a JsonResponse with grouped count results or null ( Caching is applied for performance )
     *
     * @example | $this->countSelectGroupedByFilter($request, $query, $countSelect);
     *
     *  Example URL: /select=count:post_type&filter[post_type]=question,tutorial
     * 
     * {
     *   "status": "success",
     *   "message": "Count retrieved successfully by filter values",
     *   "code": 200,
     *   "count": 1,
     *   "data": {
     *       "question": 88,
     *       "tutorial": 75,
     *   }
     * }
     */
    private function countSelectGroupedByFilter(Request $request, $query, $countSelect, $modelSchema): JsonResponse|null {
        $filter = $request->input('filter');

        if (!array_key_exists($countSelect[0], $filter)) {
            $column = $countSelect[0];
            $count = $query->count($column);
            return $this->successResponse([$column => $count], 'Count retrieved successfully');
        }

        $filterKey = $countSelect[0];
        $filterValue = $filter[$filterKey] ?? null;


        if (!in_array($filterKey, $modelSchema)) {
            return $this->errorResponse("Invalid filter key: $filterKey", 'INVALID_FILTER_KEY', 400);
        }

        if (is_string($filterValue)) {
            $filterValue = explode(',', $filterValue);
        }

        if (isset($filterKey) && isset($filterValue)) {
            $column = $countSelect[0];

            if (is_array($filterValue) && isset($filterKey)) {
                /**
                 * Remove any existing order by clauses that might cause SQL_FULL_GROUP_BY issues
                 */
                $query->getQuery()->orders = null;

                $cacheKey = $this->generateSimpleCacheKey('count_select_grouped_by_filter_' . implode('_', $filterValue));

                /**
                 * Clear the cache for the grouped count by filter for Testing
                 */
                // Cache::forget($this->generateSimpleCacheKey('count_select_grouped_by_filter_' . implode('_', $filterValue)));

                $results = $this->cacheData($cacheKey, 180, function () use ($query, $filterKey, $filterValue, $column) {
                    return $query
                        ->whereIn($filterKey, $filterValue)
                        ->select($filterKey, DB::raw('count(' . $column . ') as total_counts'))
                        ->groupBy($filterKey)
                        ->get()
                        ->pluck('total_counts', $filterKey)
                        ->toArray();
                });

                return $this->successResponse($results, 'Count retrieved successfully by filter values');
            }
        }

        return null;
    }


    /**
     * Get the sum select from the query
     * This method checks if the select parameter contains any sum: columns
     * If it does, it returns a JsonResponse with the sum of those columns
     *
     * @param Builder $query The query builder instance
     * @param array $select The select parameters from the request
     * @return JsonResponse|null Returns a JsonResponse with sum results or null if no sum columns are found
     * 
     * @example | $this->sumSelect($query, $select);
     */
    private function sumSelect($query, $select): JsonResponse|null {
        $sumSelect = [];
        foreach ($select as $value) {
            if (str_starts_with($value, 'sum:')) {
                $sumSelect[] = str_replace('sum:', '', $value);
            }
        }

        if (!empty($sumSelect)) {
            $result = [];
            foreach ($sumSelect as $column) {
                $result[$column] = $query->sum($column);
            }
            return $this->successResponse($result, 'Sum retrieved successfully');
        }

        return null;
    }

    /**
     * Modify the request select parameter
     * This method is used to modify the select parameter in the request
     * It adds requiredFields to the select parameter if they are not already present
     *
     * @param Request $request The HTTP request containing query parameters
     * @param array $requiredFields Fields that must be included in the selection
     * @param array $removeFields Fields that should be removed from the selection
     * 
     * @example | $this->modifyRequestSelect($request, ['id'], []);
     */
    protected function modifyRequestSelect(Request $request, $requiredFields = [], $removeFields = []): void {
        if ($request->has('select')) {
            $select = $this->getSelectFields($request);

            foreach ($requiredFields as $field) {
                if (!in_array($field, $select)) {
                    if ($field === 'id') {
                        array_unshift($select, $field);
                    } else {
                        $select[] = $field;
                    }
                }
            }

            if (!empty($removeFields)) {
                $select = array_diff($select, $removeFields);
            }

            $request->query->set('select', $select);
        }
    }

    /**
     * Modify the request select fields
     * This method modifies the request select fields to remove the specified field
     * 
     * @param Request $request The HTTP request containing query parameters
     * @param array $fields The fields to remove from the select parameter
     * @return void
     * 
     * @example | $this->removeFromSelect($request, 'tags');
     */
    protected function removeFromSelect(Request $request, array $fields): void {
        if ($request->has('select')) {
            if (is_string($request->input('select'))) {
                $select = explode(',', $request->input('select'));
            } else {
                $select = $request->input('select', []);
            }
            $request->merge(['select' => array_diff($select, $fields)]);
        }
    }

    /**
     * This method checks if the specified field is selected in the request
     * 
     * @param Request $request
     * @param string $field The field to check
     * @return bool Returns true if the field is selected, false otherwise
     * 
     * @example | $this->isSelected($request, 'tags');
     */
    protected function isSelected(Request $request, string $field): bool {
        if ($request->has('select')) {
            $select = $request->input('select');
            $selectArray = is_array($select) ? $select : explode(',', $select);

            return in_array($field, $selectArray);
        }
        return false;
    }


    /**
     * Get select fields from request and parse them into an array
     * 
     * Handles both string format ('id,name,email') and array format.
     * 
     * Example:
     *   If request has ?select=id,name,email
     *   Returns ['id', 'name', 'email']
     * 
     * @param Request $request
     * @return array|null Array of fields or null if no select parameter
     * 
     * @example | $this->getSelectFields($request);
     */
    protected function getSelectFields(Request $request): array|null {
        if ($request->has('select')) {
            $select = $request->query('select');
            if (is_string($select)) {
                return explode(',', $select);
            }
            return $select;
        }
        return null;
    }

    /**
     * Get the selected fields for a relation from the request
     * 
     * @param Request $request
     * @param string $tableName The name of the table
     * @param array $defaultColumns The default columns to select
     * @param string $relation The name of the relation
     * @return array The selected fields
     * 
     * @example | $this->getSelectRelationFields($request, 'tags', ['id', 'name'], 'tags')
     */
    private function getSelectRelationFields(Request $request, string $tableName, array $defaultColumns, string $relation): array {
        if ($request->has("{$relation}_fields")) {
            $selectedFields = [];
            $fields = $request->input("{$relation}_fields");

            if (!is_array($fields)) {
                $fields = explode(',', $fields);
            }

            foreach ($fields as $key => $value) {
                $valueCheck = "$tableName.$value as $value";
                if (in_array($valueCheck, $defaultColumns)) {
                    $selectedFields[$key] = "$tableName.$value as $value";
                }
            }
            return $selectedFields;
        }
        return $defaultColumns;
    }


    /**
     * Control visible fields for models and collections
     * 
     * This method serves as a dispatcher that applies field visibility rules to 
     * different types of data structures. It handles both individual models (Comment/Post) 
     * and collections of models (Collection/LengthAwarePaginator).
     * 
     * For each applicable model, it delegates the field visibility logic to applyVisibleFields.
     * 
     * @param Request $request The HTTP request containing the 'select' parameter
     * @param array|null $originalSelectFields The original select fields from the request
     * @param mixed $data The data to process (Collection, LengthAwarePaginator, Comment, or Post)
     * @return mixed The processed data with visibility rules applied
     * 
     * @example | $query = $this->controlVisibleFields($request, $originalSelectFields, $query);
     */
    protected function controlVisibleFields(Request $request, $originalSelectFields, $data): mixed {
        if ($data instanceof Collection || $data instanceof LengthAwarePaginator) {
            foreach ($data as $model) {
                $this->applyFieldsToModelAndRelations($request, $originalSelectFields, $model);
            }
        } else if ($data instanceof Model) {
            $this->applyFieldsToModelAndRelations($request, $originalSelectFields, $data);
        }
        return $data;
    }


    /**
     * Apply field visibility to a model and its relations
     * 
     * This helper method applies visibility rules to a single model
     * and its parent/children relations. It processes field visibility
     * in a structured way across multiple related entities.
     * 
     * Like the TARDIS in Doctor Who, this method travels through time:
     * - It visits the present (the current model)
     * - It travels to the future (its children) 
     * - It looks into the past (its parent)
     * All while making sure temporal paradoxes (infinite loops) don't occur
     * because each model instance exists only once in memory, even if
     * it appears in multiple places in the relationship timeline.
     * 
     * The method works with up to three levels of nesting:
     * 1. The main model itself
     * 2. Its immediate children and parent
     * 3. For each child, it processes its parent reference
     * 
     * This approach ensures consistent field visibility across
     * the entire comment thread hierarchy.
     * 
     * @param Request $request The HTTP request
     * @param array|null $originalSelectFields The original select fields
     * @param mixed $model The model to process
     * 
     * @example | $this->applyFieldsToModelAndRelations($request, $originalSelectFields, $model);
     */
    private function applyFieldsToModelAndRelations(Request $request, $originalSelectFields, $model): void {
        // Process the current model
        $this->applyVisibleFields($request, $originalSelectFields, $model);

        // Process children (if they exist)
        if (isset($model->children) && $model->children) {
            // Process the children collection
            $this->applyVisibleFields($request, $originalSelectFields, $model->children);

            // Process each child individually
            foreach ($model->children as $child) {
                $this->applyVisibleFields($request, $originalSelectFields, $child);

                // Process the grandchildren (if they exist)
                if (isset($child->children) && $child->children) {
                    // Process the grandchildren collection
                    $this->applyVisibleFields($request, $originalSelectFields, $child->children);

                    // Process each grandchild individually
                    foreach ($child->children as $grandchild) {
                        $this->applyVisibleFields($request, $originalSelectFields, $grandchild);

                        // Process the grandchild's parent (if it exists and isn't already processed)
                        if (isset($grandchild->parent) && $grandchild->parent) {
                            $this->applyVisibleFields($request, $originalSelectFields, $grandchild->parent);
                        }
                    }
                }

                // Process the child's parent (if it exists and isn't already processed)
                if (isset($child->parent) && $child->parent) {
                    $this->applyVisibleFields($request, $originalSelectFields, $child->parent);
                }
            }
        }

        // Process parent (if it exists)
        if (isset($model->parent) && $model->parent) {
            $this->applyVisibleFields($request, $originalSelectFields, $model->parent);
        }
    }


    /**
     * Apply visible fields to the model
     * 
     * This method controls field visibility based on the 'select' parameter.
     * The implementation works in a somewhat counterintuitive way:
     * 1. It first identifies fields that are in the user's select request but NOT in originalSelectFields
     * 2. It then HIDES these additional fields with makeHidden()
     *
     * In practice, this means the model shows:
     * - All fields from originalSelectFields
     * - The 'id' field (always included)
     * - But hides any extra fields that might be in the select parameter
     *
     * @param Request $request The HTTP request containing the 'select' parameter
     * @param array|null $originalSelectFields The original select fields from the request
     * @param mixed $model The model to process
     * @return mixed The processed model with visible fields
     * 
     * @example | $this->applyVisibleFields($request, $originalSelectFields, $model);
     */
    private function applyVisibleFields(Request $request, $originalSelectFields, $model): mixed {
        if ($request->has('select')) {
            $select = $this->getSelectFields($request);
            $visibleFields = array_merge($originalSelectFields ?? [], ['id']);
            $fieldsToHide = array_diff($select, $visibleFields);

            foreach ($fieldsToHide as $field) {
                $model->makeHidden($field);
            }
        }
        return $model;
    }
}
