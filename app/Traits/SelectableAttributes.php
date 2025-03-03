<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Traits\ApiResponses;

trait SelectableAttributes {
    public function selectAttributes(Request $request, Builder $query, array $allowedAttributes = []): JsonResponse|Builder {
            
        if ($request->query('select') !== null) {
            // Get the select string or array from the request
            $select = $request->query('select', []);

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
}
