<?php

namespace App\Traits;

use Illuminate\Http\Request;


/**
 * Trait for managing database query access filters based on user permissions.
 * Used to limit data access based on user role and ownership.
 */
trait AccessFilter {

    /**
     * Apply post access filters to query based on user role
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Builder
     * 
     * @example | $query = $this->applyPostAccessFilters($request, $query);
     */
    protected function applyPostAccessFilters(Request $request, $query) {
        $user = $this->getAuthenticatedUser($request);
        if (!$user) {
            $query->where('status', 'published')->where('reports_count', '<', 5);
        } elseif ($user->role !== 'admin' && $user->role !== 'moderator') {
            $query->where(function ($subQuery) use ($user) {
                $subQuery->where('user_id', $user->id)->orWhere(function ($subsubQuery) {
                    $subsubQuery->where('status', 'published')->where('reports_count', '<', 5);
                });
            });
        }
        return $query;
    }
}
