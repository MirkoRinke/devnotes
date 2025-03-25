<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

trait RelationLoader {
    /**
     * Load a relation only if specific columns are selected
     * 
     * @param Request $request
     * @param Builder $query
     * @param string $relation Name of the relation to load
     * @param string $foreignKey Key that must be present in selected columns
     * @param array $columns Columns to select from the related model
     * @return Builder
     */
    protected function loadRelation(Request $request, Builder $query, string $relation, string $foreignKey, array $columns = ['*']) {
        $selectParameter = $request->input('select');
        $selectArray = [];

        if (is_string($selectParameter)) {
            $selectArray = explode(',', $selectParameter);
        } elseif (is_array($selectParameter)) {
            $selectArray = $selectParameter;
        }

        if (empty($selectArray) || in_array($foreignKey, $selectArray)) {
            $query = $query->with([$relation => function ($query) use ($columns) {
                $query->select($columns);
            }]);
        }

        return $query;
    }
}
