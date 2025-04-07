<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Traits\ApiResponses;

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
    function modifyRequestSelect(Request $request) {
        if ($request->has('select')) {

            $select = $this->getSelectFields($request);

            $requiredFields = ['id', 'reports_count'];

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
    protected function getSelectFields(Request $request) {
        $select = $request->query('select');
        if (is_string($select)) {
            return explode(',', $select);
        }
        return $select;
    }
}
