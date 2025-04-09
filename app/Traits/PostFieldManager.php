<?php

namespace App\Traits;

use Illuminate\Http\Request;

use App\Models\Post;

use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

use App\Services\ExternalSourceService;

/**
 * @requires \App\Traits\AuthHelper for getUserFromToken method in the controller
 */
trait PostFieldManager {

    /**
     * Get the ExternalSourceService instance
     */
    protected function getExternalSourceService(): ExternalSourceService {
        return app(ExternalSourceService::class);
    }

    /**
     * Apply access filters to query based on user role
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyAccessFilters(Request $request, $query) {
        $user = $this->getUserFromToken($request);
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



    /**
     * Manage field visibility.
     * 
     * @param Request $request
     * @param mixed $data
     * @return mixed
     */
    protected function manageFieldVisibility(Request $request, $data): mixed {
        $user = $this->getUserFromToken($request);

        if (!$user || ($user->role !== 'admin' && $user->role !== 'moderator')) {
            $data->makeHidden('moderation_info');
        }

        if (!$this->getExternalSourceService()->shouldDisplayExternalImages($request, $user)) {
            if ($data instanceof Collection || $data instanceof LengthAwarePaginator) {
                foreach ($data as $post) {
                    $post->images = [];
                }
            } else if ($data instanceof Post) {
                $data->images = [];
            }
        }
        return $data;
    }
}
