<?php

namespace App\Traits;

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
     */
    public function modifyRequestSelect(Request $request, $requiredFields = []): void {
        if ($request->has('select')) {
            $select = $this->getSelectFields($request);

            foreach ($requiredFields as $field) {
                if (!in_array($field, $select)) {
                    $select[] = $field;
                }
            }
            $request->query->set('select', $select);
        }
    }


    /**
     * Get select fields from request and parse them into an array
     * 
     * @param Request $request
     * @return array|null
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
     * Control visible fields across models in response
     * 
     * This method handles the visibility of fields in both single models and collections.
     * It ensures only fields that were originally requested are visible in the final 
     * response, reverting any fields that were added for internal processing purposes.
     * 
     * @param Request $request The HTTP request with select parameter
     * @param array|null $originalSelectFields The original select fields before modification
     * @param mixed $comment The model or collection to process
     * @return mixed The processed model or collection with controlled visibility
     */
    public function controlVisibleFields(Request $request, $originalSelectFields, $comment): mixed {
        if ($comment instanceof Collection || $comment instanceof LengthAwarePaginator) {
            foreach ($comment as $c) {
                $this->applyVisibleFields($request, $originalSelectFields, $c);
            }
        } else {
            $this->applyVisibleFields($request, $originalSelectFields, $comment);
        }
        return $comment;
    }


    /**
     * Apply field visibility rules to a single model and its relations
     * 
     * This method compares the current select fields with the original requested fields
     * to determine which fields were added for internal processing. It then hides these
     * additional fields in the main model and recursively in all children and their
     * relationships to maintain consistent response structure.
     * 
     * The method ensures the ID field is always preserved regardless of selection.
     * 
     * @param Request $request The HTTP request containing select parameter
     * @param array|null $originalSelectFields The original select fields before modification
     * @param mixed $comment The model to process
     * @return mixed The processed model with updated field visibility
     */
    protected function applyVisibleFields(Request $request, $originalSelectFields, $comment): mixed {
        if ($request->has('select')) {
            $select = $this->getSelectFields($request);
            $visibleFields = array_merge($originalSelectFields, ['id']);
            $fieldsOnlyInSelect = array_diff($select, $visibleFields);

            foreach ($fieldsOnlyInSelect as $field) {
                $comment->makeHidden($field);
                if (isset($comment->children) && $comment->children) {
                    foreach ($comment->children as $child) {
                        if (method_exists($child, 'setVisible')) {
                            $child->makeHidden($field);
                            $child->parent->makeHidden($field);
                        }
                        if (isset($child->children) && $child->children) {
                            $this->applyVisibleFields($request, $originalSelectFields, $child);
                        }
                    }
                }
            }
            return $comment;
        }
        return $comment;
    }
}
