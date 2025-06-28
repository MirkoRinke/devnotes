<?php

namespace App\Traits;

use App\Models\Comment;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * This ApiInclude Trait Provides methods to dynamically include relationships in API responses based on request parameters.
 * This trait supports selective loading of relations, fields filtering, and recursive relationship handling
 * for nested resources like comments with children.
 * 
 */
trait ApiInclude {

    /**
     * Get relation key fields from request
     * 
     * This method retrieves the relation key fields based on the 'include' parameter
     * in the request. It maps the relations to their corresponding keys using the 
     * provided relationToKeyMap.
     *
     * @param Request $request The HTTP request containing the 'include' parameter
     * @param array $relationToKeyMap An associative array mapping relations to keys
     * @return array An array of required keys based on the included relations
     * 
     * @example | $relationKeyFields = $this->getRelationKeyFields($request, ['user' => 'user_id']);
     */
    protected function getRelationKeyFields(Request $request, array $relationToKeyMap = []) {
        if ($request->has('include')) {
            $relations = explode(',', $request->input('include'));
            $requiredKeys = [];

            foreach ($relations as $relation) {
                if (array_key_exists($relation, $relationToKeyMap)) {
                    $requiredKeys[] = $relationToKeyMap[$relation];
                }
            }
            return $requiredKeys;
        }
        return [];
    }

    /**
     * Get relation fields from request
     * 
     * This method retrieves the relation fields based on the 'include' parameter
     * in the request. It checks if the relation fields are specified in the request
     * and merges them with the required fields.
     * 
     * @param Request $request The HTTP request containing the 'include' parameter
     * @param string $relation The name of the relation to check
     * @param array $requiredFields An array of required fields to include
     * 
     * @return array|null An array of fields to include or null if relation fields 
     *                   are not specified AND no allowedFields are provided
     * 
     * @example | $this->getRelationFieldsFromRequest($request, 'user', [], ['id', 'display_name', 'role', 'created_at', 'updated_at', 'is_banned', 'was_ever_banned', 'moderation_info']);
     */
    protected function getRelationFieldsFromRequest(Request $request, string $relation, array $requiredFields = [], array $allowedFields = []): array | null {
        if ($request->has('include') && $request->has($relation . '_fields')) {
            $fields = $request->input($relation . '_fields');

            $requiredFields = array_unique(array_merge($requiredFields, ['id']));

            if (is_array($fields)) {
                $fields = $allowedFields !== ['*'] ? array_intersect($fields, $allowedFields) : $fields;
                return array_unique(array_merge($fields, $requiredFields));
            } else {
                $fieldArray = explode(',', $fields);
                $fieldArray = $allowedFields !== ['*'] ? array_intersect($fieldArray, $allowedFields) : $fieldArray;
                return array_unique(array_merge($fieldArray, $requiredFields));
            }
        }

        // If $allowedFields is empty, return null else return the allowed fields
        return empty($allowedFields) ? null : $allowedFields;
    }


    /**
     * Make relations visible based on 'include' parameter in request
     * 
     * This method processes the 'include' parameter to make specified relations 
     * visible in the model or collection. It supports both single models and 
     * Collections/LengthAwarePaginator, applying visibility rules recursively.
     *
     * @param Request $request The HTTP request containing the 'include' parameter
     * @param mixed $data The model, collection or lengthAwarePaginator to process
     * @return mixed The processed $data with visible relations
     * 
     * @example | $user = $this->checkForIncludedRelations($request, $user);
     */
    protected function checkForIncludedRelations(Request $request, $data): mixed {
        if ($request->has('include')) {
            $relations = explode(',', $request->input('include'));
            $select = $this->getSelectFields($request) ?? [];
            $this->applyRelationVisibility($request, $data, $relations, $select);
            return $data;
        }
        return $data;
    }

    /**
     * Apply relation visibility based on request
     * 
     * This method applies visibility rules to the model based on the specified relations
     * and the request. It makes the specified relations visible and applies additional
     * visibility rules for nested relations (e.g., children) and parent fields in comments.
     * 
     * @param Request $request The HTTP request containing the 'include' parameter
     * @param mixed $model The model to apply visibility rules to
     * @param array $relations An array of relations to make visible
     * @param array $select An array of selected fields from the request
     * @return void
     * 
     * @example | $this->applyRelationVisibility($request, $data, $relations, $select);
     */
    private function applyRelationVisibility(Request $request, $model, array $relations, array $select): void {

        if ($model instanceof Collection || $model instanceof LengthAwarePaginator) {
            foreach ($model as $item) {
                $this->applyRelationVisibility($request, $item, $relations, $select);
            }
            return;
        }

        $model->makeVisible($relations);

        // If 'children' relation is requested, apply recursive visibility rules to all nested comments
        if (in_array('children', $relations)) {
            $this->applyChildrenRelationFieldsVisibility($request, $model, $relations, $select);
        }

        // For Comment models, handle parent comment visibility settings
        if ($model instanceof Comment) {
            $this->applyParentFieldsVisibilityInComments($request, $model, $relations, $select);
        }
    }


    /**
     * Determine which fields to include based on request input
     * 
     * This method decides which fields should be included in the response by following
     * a priority order:
     * 1. If $input is provided as an array, use it directly
     * 2. If $input is provided as a string (comma-separated), convert to array
     * 3. If no $input but $select is provided, use $select
     * 4. If nothing is provided, default to ['*'] (meaning "all fields")
     * 
     * @param mixed $input The field selection from request (array or comma-separated string)
     * @param array $select Fallback field selection if no input is provided
     * @return array Final list of fields to include
     * 
     * @example | $visibleFields = $this->resolveFieldSelection($request->input('user_fields'), ['id', 'name']);
     */
    private function resolveFieldSelection($input, array $select): array {
        $fieldSelection = match (true) {
            isset($input) && is_array($input) => $input,
            isset($input) => explode(',', $input),
            !empty($select) => $select,
            default => ['*']
        };
        return $fieldSelection;
    }


    /**
     * Apply visibility rules to child relations
     * 
     * This method applies visibility rules to child relations of the model. It checks
     * if the 'children' relation is present and applies visibility rules recursively.
     * 
     * @param Request $request The HTTP request containing the 'include' parameter
     * @param mixed $comment The comment model to apply visibility rules to
     * @param array $relations An array of relations to make visible
     * @param array $select An array of selected fields from the request
     * @return void
     * 
     * @example | $this->applyChildrenRelationFieldsVisibility($request, $comment, $relations, $select);
     */
    private function applyChildrenRelationFieldsVisibility(Request $request, $comment, array $relations, array $select): void {
        $input = $request->input('children_fields');
        $allowedFields = $this->resolveFieldSelection($input, $select);
        $childrenVisibleFields = $this->getRelationFieldsFromRequest($request, 'children', [], $allowedFields);
        $childRelations = $relations;

        if (isset($comment->children) && $comment->relationLoaded('children')) {
            foreach ($comment->children as $child) {
                $childrenVisibleFields = $childrenVisibleFields == ['*'] ? array_keys($child->getAttributes()) : $childrenVisibleFields;

                if ($childrenVisibleFields) {
                    $child->setVisible($childrenVisibleFields);
                }

                $child->makeVisible($childRelations);

                if ($child->relationLoaded('children')) {
                    $this->applyChildrenRelationFieldsVisibility($request, $child, $relations, $select);
                }
            }
        }
    }


    /**
     * Apply visibility rules to parent fields in comments
     * 
     * This method applies visibility rules to parent fields in comments,
     * similar to how child relations are handled. It checks if the 'parent'
     * relation is present and applies visibility rules accordingly.
     * 
     * @param Request $request The HTTP request containing the 'include' parameter
     * @param mixed $comment The comment model to apply visibility rules to
     * @param array $relations An array of relations to make visible
     * @param array $select An array of selected fields from the request
     * @return void
     * 
     * @example | $this->applyParentFieldsVisibilityInComments($request, $comment, $relations, $select);
     */
    private function applyParentFieldsVisibilityInComments(Request $request, $comment, array $relations = [], array $select = []): void {
        $input = $request->input('parent_fields');
        $allowedFields = $this->resolveFieldSelection($input, $select);
        $parentVisibleFields = $this->getRelationFieldsFromRequest($request, 'parent', [], $allowedFields);

        $this->applyParentVisibilityToComment($comment, $parentVisibleFields, $relations);

        if (isset($comment->children) && $comment->relationLoaded('children')) {
            foreach ($comment->children as $child) {
                $this->applyParentVisibilityToComment($child, $parentVisibleFields, $relations);

                if ($child->relationLoaded('children')) {
                    $this->applyParentFieldsVisibilityInComments($request, $child, $relations, $select);
                }
            }
        }
    }


    /**
     * Apply parent visibility rules to a comment
     * 
     * This helper method applies visibility rules to a comment's parent relation.
     * It handles two cases:
     * 1. If parentVisibleFields contains ['*'], it includes all parent attributes
     * 2. Otherwise, it sets only the specified fields as visible
     * It also adds any relations that should be visible on the parent.
     * 
     * @param mixed $comment The comment object with a parent relation
     * @param array $parentVisibleFields Fields to make visible on the parent
     * @param array $relations Additional relations to make visible
     * @return void
     * 
     * @example | $this->applyParentVisibilityToComment($comment, $parentVisibleFields, $relations);
     */
    private function applyParentVisibilityToComment($comment, array $parentVisibleFields, array $relations): void {
        if (isset($comment->parent) && method_exists($comment->parent, 'setVisible')) {
            $parentVisibleFields = $parentVisibleFields == ['*'] ? array_keys($comment->parent->getAttributes()) : $parentVisibleFields;

            if ($parentVisibleFields) {
                $comment->parent->setVisible($parentVisibleFields);
            }

            if (!empty($relations)) {
                $comment->parent->makeVisible($relations);
            }
        }
    }
}
