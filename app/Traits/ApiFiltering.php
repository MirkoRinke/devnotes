<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

use Illuminate\Http\JsonResponse;

use App\Traits\ApiResponses;


trait ApiFiltering {
    
    use ApiResponses;

    /**
     * Filter the query based on the request
     * 
     * @param Request $request
     * @param Builder $query
     * @param array $allowedFilterColumns
     * 
     * @return JsonResponse|Builder
     */
    public function filter(Request $request,Builder $query, $allowedFilterColumns = []): JsonResponse|Builder{

        $filterArray = $request->query('filter', []);
   
        foreach ($filterArray as $key => $values) {
            if (!in_array($key, $allowedFilterColumns)) {
                return $this->errorResponse('Invalid filter column: ' . $key, ['filter' => 'INVALID_FILTER_COLUMN'], 400);
            }

            // If the value is not an array, convert it to an array
            if (!is_array($values)) {
                $values = explode(',', $values); // Convert comma separated string to array
            }
   
            // For each value, add a where clause to the query to filter the results
            foreach ($values as $value) {
                $query->orWhere($key, 'LIKE', '%' . $value . '%');
            }

        }

        return $query;
    }
    
}
