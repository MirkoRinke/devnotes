<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait QueryBuilder {
    /**
     * @param Request $request
     * @param Builder $query
     * @param array $methods
     * @return JsonResponse|Collection|LengthAwarePaginator
     */
    private function buildQuery(Request $request, Builder $query, array $methods): JsonResponse|Collection|LengthAwarePaginator {
        foreach ($methods as $method => $params) {
            $query = $this->$method($request, $query, $params);
            if ($query instanceof JsonResponse) {
                return $query;
            }
        }
        return $query;
    }
}
