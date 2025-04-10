<?php

namespace App\Traits;

use App\Models\Comment;
use App\Models\Post;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

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
     * @return array|null An array of fields to include or null if not specified
     */
    protected function getRelationFieldsFromRequest(Request $request, string $relation, array $requiredFields = []): array | null {
        if ($request->has('include') && $request->has($relation . '_fields')) {
            $fields = $request->input($relation . '_fields');

            $requiredFields = array_unique(array_merge($requiredFields, ['id']));

            if (is_array($fields)) {
                return array_unique(array_merge($fields, $requiredFields));
            } else {
                $fieldArray = explode(',', $fields);
                return array_unique(array_merge($fieldArray, $requiredFields));
            }
        }
        return null;
    }


    /**
     * Make relations visible based on 'include' parameter in request
     * 
     * This method processes the 'include' parameter to make specified relations 
     * visible in the model or collection. It supports both single models and 
     * Collections/LengthAwarePaginator, applying visibility rules recursively.
     *
     * @param Request $request The HTTP request containing the 'include' parameter
     * @param mixed $target The model, collection or lengthAwarePaginator to process
     * @return mixed The processed target with visible relations
     */
    public function checkForIncludedRelations(Request $request, $target): mixed {
        if ($request->has('include')) {
            $relations = explode(',', $request->input('include'));
            $select = $this->getSelectFields($request) ?? [];

            if ($target instanceof Collection || $target instanceof LengthAwarePaginator) {
                foreach ($target as $item) {
                    $this->applyRelationVisibility($request, $item, $relations, $select);
                }
                return $target;
            } else if ($target instanceof Comment || $target instanceof Post) {
                $this->applyRelationVisibility($request, $target, $relations, $select);
                return $target;
            }
        }
        return $target;
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
     */
    protected function applyRelationVisibility(Request $request, $model, array $relations, array $select): void {
        $model->makeVisible($relations);

        if (in_array('children', $relations)) {
            $this->applyChildrenRelationFieldsVisibility($request, $model, $relations, $select);
        }
        if ($model instanceof Comment) {
            $this->applyParentFieldsVisibilityInComments($request, $model);
        }
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
     */
    protected function applyChildrenRelationFieldsVisibility(Request $request, $comment, array $relations, array $select): void {
        $childrenVisibleFields = $this->getRelationFieldsFromRequest($request, 'children');
        $childRelations = $relations;

        if (isset($comment->children) && $comment->children) {
            foreach ($comment->children as $child) {

                if ($childrenVisibleFields) {
                    $child->setVisible($childrenVisibleFields);
                }

                $child->makeVisible($childRelations);

                if (isset($child->children) && $child->children) {
                    $this->applyChildrenRelationFieldsVisibility($request, $child, $relations, $select);
                }
            }
        }
    }


    /**
     * Apply visibility rules to parent fields in comments
     * 
     * This method applies visibility rules to parent fields in comments. It checks
     * if the 'parent' relation is present and applies visibility rules recursively.
     * 
     * @param Request $request The HTTP request containing the 'include' parameter
     * @param mixed $comment The comment model to apply visibility rules to
     * @return void
     */
    protected function applyParentFieldsVisibilityInComments(Request $request, $comment): void {
        $parentVisibleFields = $this->getRelationFieldsFromRequest($request, 'parent');

        if (isset($comment->children) && $comment->children) {
            foreach ($comment->children as $child) {

                if (isset($child->parent) && method_exists($child->parent, 'setVisible') && $parentVisibleFields) {
                    $child->parent->setVisible($parentVisibleFields);
                }

                if (isset($child->children) && $child->children) {
                    $this->applyParentFieldsVisibilityInComments($request, $child);
                }
            }
        }
    }
}
