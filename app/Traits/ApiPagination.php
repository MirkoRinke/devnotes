<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Traits\ApiResponses;

/**
 * This ApiPagination Trait provides a method to paginate a query based on the request parameters.
 * It checks if the page and per_page parameters are valid and applies them to the query.
 */
trait ApiPagination {

    /**
     *  The traits used in the Trait
     */
    use ApiResponses;

    /**
     * Paginate the query results and return the results to the client
     * 
     * @param Request $request
     * @param Builder $query
     * @param int $perPage
     * @return JsonResponse|Collection|LengthAwarePaginator
     * 
     * @example | $this->getPerPage($request, $query, (int) $config);
     */
    public function getPerPage(Request $request, Builder $query, int $perPage = 10): JsonResponse|Collection|LengthAwarePaginator {
        // Get the page parameter from the request or default to 1
        $page = $request->get('page', 1);

        // Get the per_page parameter from the request
        $perPage = $request->get('per_page', $perPage);

        $perPage = $perPage < 1 ? 10 : $perPage; // If perPage is less than 1, set it to 10
        $perPage = $perPage > 100 ? 100 : $perPage; // If perPage is greater than 100, set it to 100

        // Validate the page parameter
        if (!is_numeric($page) || $page < 1) {
            return $this->errorResponse('The page parameter must be a number greater than 0', ['page' => 'INVALID_PAGE_NUMBER'], 400);
        }

        // Validate the per_page parameter
        if (!is_numeric($perPage) || $perPage < 1) {
            return $this->errorResponse('The per_page parameter must be a number greater than 0', ['per_page' => 'INVALID_PER_PAGE_NUMBER'], 400);
        }

        // Always paginate the query results
        return $query->paginate((int) $perPage);
    }
}
