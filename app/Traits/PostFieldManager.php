<?php

namespace App\Traits;

use Illuminate\Http\Request;

use App\Models\Post;

use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

use App\Traits\AuthHelper;
use App\Traits\ApiSelectable;

use App\Services\ExternalSourceService;

/**
 * @requires \App\Traits\AuthHelper for getUserFromToken method in the controller
 */
trait PostFieldManager {

    /**
     *  The traits used in the controller
     */
    use AuthHelper, ApiSelectable;

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
     * Manages visibility of fields in post data based on user permissions and settings
     *
     * @param Request $request The current HTTP request
     * @param mixed $data The data to filter (can be Post, Collection, or LengthAwarePaginator)
     * @return mixed The filtered data with appropriate field visibility
     */
    protected function manageFieldVisibility(Request $request, $data): mixed {
        $user = $this->getUserFromToken($request);

        $data = $this->hideModeratorFields($user, $data);
        $data = $this->filterExternalContent($request, $user, $data);

        return $data;
    }


    /**
     * Hide fields only visible to moderators
     * 
     * @param mixed $user
     * @param mixed $data
     * @return mixed
     */
    protected function hideModeratorFields($user, $data): mixed {
        if (!$user || ($user->role !== 'admin' && $user->role !== 'moderator')) {
            $data->makeHidden('moderation_info');
        }

        return $data;
    }


    /**
     * Filter external content based on user settings
     * 
     * @param Request $request
     * @param mixed $user
     * @param mixed $data
     * @return mixed
     */
    protected function filterExternalContent(Request $request, $user, $data): mixed {
        $types = ['images', 'videos', 'resources'];

        $selectedFields = $this->getSelectFields($request);

        foreach ($types as $type) {
            if (!$selectedFields || in_array($type, $selectedFields)) {
                if (!$this->getExternalSourceService()->shouldDisplayExternals($request, $user, $type)) {
                    if ($data instanceof Collection || $data instanceof LengthAwarePaginator) {
                        foreach ($data as $post) {
                            if ($post->user_id !== $user->id) {
                                $post->{$type} = [];
                            }
                        }
                    } else if ($data instanceof Post) {
                        if ($data->user_id !== $user->id) {
                            $data->{$type} = [];
                        }
                    }
                }
            }
        }
        return $data;
    }
}
