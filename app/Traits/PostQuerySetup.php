<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait PostQuerySetup {

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

        $query = $this->applyAccessFilters($request, $query);

        $relationKeyFields = $this->getRelationKeyFields($request, ['user' => 'user_id']);

        $this->modifyRequestSelect($request, [...['id'], ...$relationKeyFields]);

        $query = $this->loadRelations($request, $query, [
            ['relation' => 'user', 'foreignKey' => 'user_id', 'columns' => $this->getRelationFieldsFromRequest($request, 'user', [], ['id', 'display_name', 'role', 'created_at', 'updated_at', 'is_banned', 'was_ever_banned', 'moderation_info'])],

        ]);

        $query = $this->$methods($request, $query, 'post');
        if ($query instanceof JsonResponse) {
            return $query;
        }

        return $query;
    }
}
