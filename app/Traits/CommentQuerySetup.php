<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;



trait CommentQuerySetup {

    /**
     * Setup the comment query
     * This method is used to set up the query for the comments
     * It applies sorting, filtering, selecting, and pagination
     * It also loads the relations for the comments
     * 
     * @param Request $request
     * @param $query The query builder instance (used for both collections and single models)
     * @param $methods The query builder method to use ('buildQuery' or 'buildQuerySelect')
     * @return mixed The modified query builder instance
     * 
     * @example | $query = $this->setupCommentQuery($request, $query, 'buildQuery');
     */
    protected function setupCommentQuery(Request $request, $query, $methods): mixed {

        $relationKeyFields = $this->getRelationKeyFields($request, ['children' => 'id', 'parent' => 'parent_id',  'user' => 'user_id']);

        $this->modifyRequestSelect($request, [...['id', 'parent_id', 'reports_count'], ...$relationKeyFields], ['is_liked']);

        $query = $this->loadUserRelation($request, $query, 'user_id');

        // These relationships are loaded unconditionally as they're needed for internal logic
        $this->loadRelations($request, $query, [
            ['relation' => 'parent', 'foreignKey' => 'parent_id', 'columns' => ['*']],

            ['relation' => 'children', 'foreignKey' => 'parent_id', 'columns' => ['*']],
            ['relation' => 'children.user', 'foreignKey' => 'user_id', 'columns' => $this->getRelationFieldsFromRequest($request, 'user', [], ['id', 'display_name', 'role', 'avatar_items', 'created_at', 'updated_at', 'is_banned', 'was_ever_banned', 'moderation_info'])],
            ['relation' => 'children.parent', 'foreignKey' => 'parent_id', 'columns' => ['*']],

            ['relation' => 'children.children', 'foreignKey' => 'parent_id', 'columns' => ['*']],
            ['relation' => 'children.children.user', 'foreignKey' => 'user_id', 'columns' => $this->getRelationFieldsFromRequest($request, 'user', [], ['id', 'display_name', 'role', 'avatar_items', 'created_at', 'updated_at', 'is_banned', 'was_ever_banned', 'moderation_info'])],
            ['relation' => 'children.children.parent', 'foreignKey' => 'parent_id', 'columns' => ['*']],
        ]);


        /**
         * Use the query builder methods to build the query
         */
        $query = $this->$methods($request, $query, 'comment');
        if ($query instanceof JsonResponse && $query->getStatusCode() === 400) {
            return $query;
        }
        return $query;
    }
}
