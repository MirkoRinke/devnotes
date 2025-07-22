<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

use Exception;

/**
 * This RelationLoader Trait provides methods to efficiently load both standard and polymorphic relations in a query builder. 
 * It supports selective column loading based on request parameters and optimizes database queries by only 
 * loading relations when their foreign keys are included in the selection.
 */
trait RelationLoader {

    /**
     * Load the user relation
     * 
     * @param Request $request
     * @param mixed $query Builder|LengthAwarePaginator|Collection
     * @return mixed Builder|LengthAwarePaginator|Collection
     * 
     * @example | $this->loadUserRelation($request, $query, 'user_id');
     */
    private function loadUserRelation(Request $request, $query, string $foreignKey): mixed {
        if ($request->has('include') && in_array('user', explode(',', $request->input('include')))) {
            $query = $this->loadRelations($request, $query, [
                ['relation' => 'user', 'foreignKey' => $foreignKey, 'columns' => $this->getRelationFieldsFromRequest($request, 'user', [], ['id', 'display_name', 'role', 'created_at', 'updated_at', 'is_banned', 'was_ever_banned', 'moderation_info'])],
            ]);
        }
        return $query;
    }


    /**
     * Load the user relation
     * 
     * @param Request $request
     * @param mixed $query 
     * @return mixed
     * 
     * @example | $query = $this->loadProfileRelation($request, $query, 'id');
     */
    private function loadProfileRelation(Request $request, $query, string $foreignKey): mixed {
        if ($request->has('include') && in_array('profile', explode(',', $request->input('include')))) {
            $query = $this->loadRelations($request, $query, [
                ['relation' => 'profile', 'foreignKey' =>  $foreignKey, 'columns' => $this->getRelationFieldsFromRequest($request, 'profile', [], ['*'])],
            ]);
        }
        return $query;
    }


    /**
     * Load multiple relations based on selected columns
     *
     * @param Request $request
     * @param Builder $query
     * @param array $relationConfig Array of relation configurations
     * @return Builder
     * 
     * @example | $query = $this->loadRelations(
     *              $request, 
     *              $query, 
     *              [
     *                  ['relation' => 'user', 'foreignKey' => 'user_id', 'columns' => $this->getRelationFieldsFromRequest($request, 'user', [], ['id', 'display_name', 'role', 'created_at', 'updated_at', 'is_banned', 'was_ever_banned', 'moderation_info'])],
     *                  ['relation' => 'profile', 'foreignKey' => 'id', 'columns' => $this->getRelationFieldsFromRequest($request, 'profile', [], ['*'])]
     *              ]
     *            );
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
            $columns = $config['columns'] ?? ['id'];

            if (empty($selectArray) || in_array($foreignKey, $selectArray)) {
                $query = $query->with([$relation => function ($query) use ($columns) {
                    $query->select($columns);
                }]);
            }
        }

        return $query;
    }

    /**
     * Load polymorphic relations based on selected columns
     *
     * @param Request $request The HTTP request
     * @param Builder|Collection|LengthAwarePaginator $query The query builder or collection
     * @param string $relationship Name of the polymorphic relationship (e.g. 'likeable', 'reportable')
     * @param array $allowedFieldsByModel Map of model class names to fields that should be selected
     * @return Builder|Collection|LengthAwarePaginator
     * 
     * @example | $query = $this->loadPolymorphicRelations(
     *              $request,
     *              $query,
     *              'likeable',
     *              [
     *                  Post::class => $this->getRelationFieldsFromRequest($request, 'likeable_post', [], ['*']),
     *                  Comment::class => $this->getRelationFieldsFromRequest($request, 'likeable_comment', [], ['*']),
     *              ]
     *           );
     */
    protected function loadPolymorphicRelations(Request $request, $query, string $relationship, array $allowedFieldsByModel) {
        if ($query instanceof JsonResponse) {
            return $query;
        }

        $groupedByModelClass = $query->groupBy($relationship . '_type');

        foreach ($groupedByModelClass as $modelClass => $itemsOfType) {
            if (!array_key_exists($modelClass, $allowedFieldsByModel)) {
                continue;
            }

            $ids = $itemsOfType->pluck($relationship . '_id')->toArray();
            if (empty($ids)) {
                continue;
            }

            try {
                $fieldsToSelect = $allowedFieldsByModel[$modelClass] ?? ['id'];

                $modelName = class_basename($modelClass);

                //TODO : Remove this workaround when the QueryParam is renamed
                if ($modelName === 'UserProfile') {
                    $modelName = 'Profile';
                }

                $originalSelectFields = $this->getRelationFieldsFromRequest($request, $relationship . "_" . lcfirst($modelName), [], ['*']);

                //TODO : Remove this workaround when the QueryParam is renamed
                if ($modelName === 'Profile') {
                    $modelName = 'UserProfile';
                }

                $relatedEntities = app($modelClass)->whereIn('id', $ids)->select($fieldsToSelect)->get()->keyBy('id');

                $foreignKey = $relationship . '_id';

                foreach ($itemsOfType as $item) {
                    if (isset($relatedEntities[$item->$foreignKey])) {
                        $item->setRelation($relationship, $relatedEntities[$item->$foreignKey]);

                        /**
                         * Manage the visibility of fields for the entity
                         */
                        $item->$relationship = $this->{"manage{$modelName}sFieldVisibility"}($request, $item->$relationship, $originalSelectFields);

                        $hiddenFields = array_diff($fieldsToSelect, $originalSelectFields);
                        $item->$relationship->makeHidden($hiddenFields);
                    }
                }
            } catch (Exception $e) {
                return $this->errorResponse('An unexpected error occurred', 'SERVER_ERROR', 500);
            }
        }

        return $query;
    }
}
