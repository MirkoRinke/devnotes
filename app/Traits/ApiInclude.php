<?php

namespace App\Traits;

use App\Models\Comment;
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
    public function checkForIncludedRelations(Request $request, $target) {
        if ($request->has('include')) {
            $relations = explode(',', $request->input('include'));
            $select = $this->getSelectFields($request);

            if ($target instanceof Collection || $target instanceof LengthAwarePaginator) {
                foreach ($target as $item) {
                    $this->applyRelationVisibility($item, $relations, $select);
                }
                return $target;
            } else if ($target instanceof Comment)
                return $this->applyRelationVisibility($target, $relations, $select);
        }
        return $target;
    }


    /**
     * Apply relation visibility settings to a single model
     * 
     * This method handles the visibility settings for a model and its relations.
     * It makes specified relations visible and processes child relations recursively,
     * applying select fields and relation visibility to each level.
     *
     * @param mixed $model The model to process
     * @param array $relations Array of relation names to make visible
     * @param array|null $select Array of fields to make visible in child models
     * @return mixed The processed model with updated visibility settings
     */
    protected function applyRelationVisibility($model, array $relations, $select) {
        $model->makeVisible($relations);

        if (in_array('children', $relations) && isset($model->children) && $model->children) {
            foreach ($model->children as $child) {

                $child->setVisible($select ?? []);

                $child->makeVisible($relations);

                if (isset($child->children) && $child->children) {
                    $this->applyRelationVisibility($child, $relations, $select);
                }
            }
        }
        return $model;
    }
}
