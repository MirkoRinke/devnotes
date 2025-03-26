<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

trait RelationLoader {
    /**
     * Load multiple relations based on selected columns
     *
     * @param Request $request
     * @param Builder $query
     * @param array $relationConfig Array of relation configurations
     * @return Builder
     */
    protected function loadRelations(Request $request, Builder $query, array $relationConfig) {
        $selectParameter = $request->input('select');
        $selectArray = [];

        if (is_string($selectParameter)) {
            $selectArray = explode(',', $selectParameter);
        } elseif (is_array($selectParameter)) {
            $selectArray = $selectParameter;
        }

        foreach ($relationConfig as $config) {
            $relation = $config['relation'];
            $foreignKey = $config['foreignKey'];
            $columns = $config['columns'] ?? ['*'];

            if (empty($selectArray) || in_array($foreignKey, $selectArray)) {
                $query = $query->with([$relation => function ($query) use ($columns) {
                    $query->select($columns);
                }]);
            }
        }

        return $query;
    }

    /**
     * Load a single relation based on selected columns
     *
     * @param Request $request
     * @param Builder $query
     * @param string $relation
     * @param string $foreignKey
     * @param array $columns
     * @return Builder
     */
    protected function loadRelation(Request $request, Builder $query, string $relation, string $foreignKey, array $columns = ['*']) {
        return $this->loadRelations($request, $query, [
            [
                'relation' => $relation,
                'foreignKey' => $foreignKey,
                'columns' => $columns
            ]
        ]);
    }
}
