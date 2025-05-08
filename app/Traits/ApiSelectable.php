<?php

namespace App\Traits;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Traits\ApiResponses;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

trait ApiSelectable {
    /**
     *  The traits used in the controller
     */
    use ApiResponses;

    /**
     * Select the columns to return in the response
     *
     * @param Request $request
     * @param Builder $query
     * @param array $allowedAttributes
     * @return JsonResponse|Builder
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
     */
    public function controlVisibleFields(Request $request, $originalSelectFields, $data): mixed {
        if ($data instanceof Collection || $data instanceof LengthAwarePaginator) {
            foreach ($data as $model) {
                $this->applyFieldsToModelAndRelations($request, $originalSelectFields, $model);
            }
        } else if ($data instanceof Comment || $data instanceof Post) {
            $this->applyFieldsToModelAndRelations($request, $originalSelectFields, $data);
        }
        return $data;
    }


    /**
     * Apply field visibility to a model and its relations
     * 
     * This helper method applies visibility rules to a single model
     * and its parent/children relations.
     * 
     * Like the TARDIS in Doctor Who, this method travels through time:
     * - It visits the present (the current model)
     * - It travels to the future (its children) 
     * - It looks into the past (its parent)
     * All while making sure temporal paradoxes (infinite loops) don't occur
     * because each model instance exists only once in memory, even if
     * it appears in multiple places in the relationship timeline.
     * 
     * @param Request $request The HTTP request
     * @param array|null $originalSelectFields The original select fields
     * @param mixed $model The model to process
     */
    private function applyFieldsToModelAndRelations(Request $request, $originalSelectFields, $model): void {
        $this->applyVisibleFields($request, $originalSelectFields, $model);

        if (isset($model->children) && $model->children) {
            $this->applyVisibleFields($request, $originalSelectFields, $model->children);

            foreach ($model->children as $child) {
                $this->applyFieldsToModelAndRelations($request, $originalSelectFields, $child);
            }
        }

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
