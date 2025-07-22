<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Traits\AccessFilter;
use App\Traits\ApiSelectable;
use App\Traits\RelationLoader;

/**
 * Trait for setting up post queries with access filters and relations.
 * This trait is used to set up the query for the post, applying access filters,
 * loading relations, and modifying the request select fields.
 */
trait PostQuerySetup {

    /**
     *  The traits used in the Trait
     */
    use AccessFilter, ApiSelectable, RelationLoader;

    /**
     * Setup the post query
     * This method is used to set up the query for the post
     * It applies the access filters.
     * It applies sorting, filtering, selecting, and pagination
     * It also loads the relations for the post
     * 
     * @param Request $request
     * @param $query
     * @param $methods (string) The method to call for building the query
     * @return mixed
     * 
     * @example | $query = $this->setupPostQuery($request, $query, 'buildQuery');
     */
    protected function setupPostQuery(Request $request, $query, string $methods): mixed {

        $query = $this->applyPostAccessFilters($request, $query);

        $relationKeyFields = $this->getRelationKeyFields($request, ['user' => 'user_id']);

        $this->modifyRequestSelect($request, [...['id'], ...$relationKeyFields], ['is_favorited', 'is_liked']);

        $query = $this->loadUserRelation($request, $query, 'user_id');

        $query = $this->loadTagsRelation($request, $query);

        $query = $this->loadLanguagesRelation($request, $query);

        $query = $this->loadTechnologiesRelation($request, $query);

        $query = $this->$methods($request, $query, 'post');
        if ($query instanceof JsonResponse) {
            return $query;
        }

        return $query;
    }

    /**
     * Load the tags relation
     * 
     * @param Request $request
     * @param mixed $query Builder|LengthAwarePaginator|Collection
     * @return mixed Builder|LengthAwarePaginator|Collection
     * 
     * @example | $this->loadTagsRelation($request, $query)
     */
    private function loadTagsRelation(Request $request, $query): mixed {

        /**
         * If the request does not have the 'select' parameter or if 'tags' is selected,
         * we will load the tags relation by default.
         */
        if (!$request->has('select') || $this->isSelected($request, 'tags')) {

            $this->removeFromSelect($request, ['tags']);

            /**
             * Load the tags relation by Default
             * 
             * Explicit table.column AS alias format is used for many-to-many relationships
             * This is to avoid ambiguity in the result set, especially when joining multiple tables.
             */
            $tableName = $query->getModel()->tags()->getRelated()->getTable();

            $defaultColumns = [
                "$tableName.id as id",
                "$tableName.name as name"
            ];

            $selectedFields = $this->getSelectRelationFields($request, $tableName, $defaultColumns, 'tags');

            $query = $this->loadRelations($request, $query, [
                ['relation' => 'tags', 'foreignKey' => 'id', 'columns' => $selectedFields],
            ]);

            return $query;
        }

        return $query;
    }


    /**
     * Load the languages relation
     * 
     * @param Request $request
     * @param mixed $query Builder|LengthAwarePaginator|Collection
     * @return mixed Builder|LengthAwarePaginator|Collection
     * 
     * @example | $this->loadLanguagesRelation($request, $query)
     */
    private function loadLanguagesRelation(Request $request, $query): mixed {

        /**
         * If the request does not have the 'select' parameter or if 'languages' is selected,
         * we will load the languages relation by default.
         */
        if (!$request->has('select') || $this->isSelected($request, 'languages')) {

            $this->removeFromSelect($request, ['languages']);

            /**
             * Load the languages relation by Default
             * 
             * Explicit table.column AS alias format is used for many-to-many relationships
             * This is to avoid ambiguity in the result set, especially when joining multiple tables.
             */
            $tableName = $query->getModel()->languages()->getRelated()->getTable();

            $defaultColumns = [
                "$tableName.id as id",
                "$tableName.name as name"
            ];

            $selectedFields = $this->getSelectRelationFields($request, $tableName, $defaultColumns, 'languages');

            $query = $this->loadRelations($request, $query, [
                ['relation' => 'languages', 'foreignKey' => 'id', 'columns' => $selectedFields],
            ]);

            return $query;
        }

        return $query;
    }


    /**
     * Load the technologies relation
     * 
     * @param Request $request
     * @param mixed $query Builder|LengthAwarePaginator|Collection
     * @return mixed Builder|LengthAwarePaginator|Collection
     * 
     * @example | $this->loadTechnologiesRelation($request, $query)
     */
    private function loadTechnologiesRelation(Request $request, $query): mixed {
        /**
         * If the request does not have the 'select' parameter or if 'technologies' is selected,
         * we will load the technologies relation by default.
         */
        if (!$request->has('select') || $this->isSelected($request, 'technologies')) {
            $this->removeFromSelect($request, ['technologies']);

            /**
             * Load the technologies relation by Default
             * 
             * Explicit table.column AS alias format is used for many-to-many relationships
             * This is to avoid ambiguity in the result set, especially when joining multiple tables.
             */
            $tableName = $query->getModel()->technologies()->getRelated()->getTable();

            $defaultColumns = [
                "$tableName.id as id",
                "$tableName.name as name"
            ];

            $selectedFields = $this->getSelectRelationFields($request, $tableName, $defaultColumns, 'technologies');

            $query = $this->loadRelations($request, $query, [
                ['relation' => 'technologies', 'foreignKey' => 'id', 'columns' => $selectedFields],
            ]);

            return $query;
        }
        return $query;
    }
}
