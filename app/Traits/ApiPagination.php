<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Traits\ApiResponses;

trait ApiPagination {

    // Import the ApiResponses trait to use the errorResponse method
    use ApiResponses;

    /**
     * Paginate the query results and return the results to the client
     * 
     * @param Request $request
     * @param Builder $query
     * @param int $default
     * @return JsonResponse|Collection|LengthAwarePaginator
     */
    public function getPerPage(Request $request, Builder $query, int $default = 10): JsonResponse|Collection|LengthAwarePaginator  {
        // Get the page and per_page parameters from the request
        $page = $request->get('page');

        if ($page) {      
            // Get the per_page parameter from the request
            $perPage = $request->get('per_page', $default);
            
            // Validate the page and per_page parameters
            if (!is_numeric($page) || $page < 1) {
                return $this->errorResponse('The page parameter must be a number greater than 0', ['page' => 'INVALID_PAGE_NUMBER'], 400);
            }

            // Validate the per_page parameter
            if (!is_numeric($perPage) || $perPage < 1) {
                return $this->errorResponse('The per_page parameter must be a number greater than 0', ['per_page' => 'INVALID_PER_PAGE_NUMBER'], 400);
            }

            // Paginate the query results and return the results to the client 
            return $query->paginate((int) $perPage);
        }
        return $query->get();
    }
}