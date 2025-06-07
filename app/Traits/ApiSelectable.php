<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Traits\ApiResponses;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * This ApiSelectable Trait provides a method to select specific columns from a query
 * based on the request parameters. It checks if the select parameters are valid and applies them to the query.
 */
trait ApiSelectable {

    /**
     *  The traits used in the Trait
     */
    use ApiResponses;

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
        // Get the select string or array from the request
        $select = $request->query('select');

        if ($select) {
            // If the select parameter is a string, convert it to an array
            if (is_string($select)) {
                $select = explode(',', $select);
            }
            // Check if the select parameter is an array
            $validAttributes = array_intersect($select, $allowedAttributes);
            // Check if there are any invalid attributes
            $invalidAttributes = array_diff($select, $allowedAttributes);
            // If there are invalid attributes, return an error response
            if (empty($validAttributes) || !empty($invalidAttributes)) {
                $invalidAttributesString = implode(', ', $invalidAttributes);
                return $this->errorResponse("Invalid select column: $invalidAttributesString", ['select' => 'INVALID_SELECT_COLUMN'], 400);
            }
            // If the id column is allowed and not in the valid attributes, add it to the beginning of the valid attributes array
            if (in_array('id', $allowedAttributes) && !in_array('id', $validAttributes)) {
                array_unshift($validAttributes, 'id');
            }
            return $query->select($validAttributes);
        }
        return $query;
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
    public function modifyRequestSelect(Request $request, $requiredFields = [], $removeFields = []): void {
        if ($request->has('select')) {
            $select = $this->getSelectFields($request);

            foreach ($requiredFields as $field) {
                if (!in_array($field, $select)) {
                    $select[] = $field;
                }
            }

            if (!empty($removeFields)) {
                $select = array_diff($select, $removeFields);
            }

            $request->query->set('select', $select);
        }
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
    public function getSelectFields(Request $request): array|null {
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
    public function controlVisibleFields(Request $request, $originalSelectFields, $data): mixed {
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
    protected function applyVisibleFields(Request $request, $originalSelectFields, $model): mixed {
        if ($request->has('select')) {
            $select = $this->getSelectFields($request);
            $visibleFields = array_merge($originalSelectFields ?? [], ['id']);
            $fieldsOnlyInSelect = array_diff($select, $visibleFields);
            foreach ($fieldsOnlyInSelect as $field) {
                $model->makeHidden($field);
            }
        }
        return $model;
    }
}
