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

        foreach ($filterArray as $key => $value) {
            if (!in_array($key, $allowedFilterColumns)) {
                return $this->errorResponse('Invalid filter column: ' . $key, ['filter' => 'INVALID_FILTER_COLUMN'], 400);
            }
            $query->where($key, 'LIKE' , '%'.$value.'%');
        }
        return $query;
    }
    
}
