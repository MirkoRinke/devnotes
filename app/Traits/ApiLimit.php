<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Traits\ApiResponses;

trait ApiLimit {

    /**
     *  The traits used in the Trait
     */
    use ApiResponses;

    /**
     * Limit the query results and return the results to the client
     * 
     * @param Request $request
     * @param Builder $query
     * @param int $limit
     * @return JsonResponse|Builder
     */
    protected function setLimit(Request $request, Builder $query, int $limit = 10): JsonResponse|Collection|LengthAwarePaginator|Builder {
        if (env('QUERY_LOGGING_ENABLED', false)) {
            return $query;
        }

        $limit = $request->get('setLimit', $limit);
        $offset = $request->get('setOffset', 0);

        if (!is_numeric($limit)) {
            return $this->errorResponse('The limit parameter must be a number greater than 0', ['limit' => 'INVALID_LIMIT_NUMBER'], 400);
        }

        if (!is_numeric($offset)) {
            return $this->errorResponse('The offset parameter must be a number greater than or equal to 0', ['offset' => 'INVALID_OFFSET_NUMBER'], 400);
        }

        $limit = $limit < 1 ? 10 : $limit;
        $limit = $limit > 100 ? 100 : $limit;

        if ($limit) {
            return $query->limit((int) $limit)->offset((int) $offset);
        }

        return $query;
    }
}
