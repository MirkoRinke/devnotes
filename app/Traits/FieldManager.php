<?php

namespace App\Traits;

use Illuminate\Http\Request;

use App\Models\Post;

use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

use App\Traits\AuthHelper;
use App\Traits\ApiSelectable;

use App\Services\ExternalSourceService;
use App\Services\CommentModerationService;

/**
 * @requires \App\Traits\AuthHelper for getUserFromToken method in the controller
 */
trait FieldManager {

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
     * Get the CommentModerationService instance
     */
    protected function getCommentModerationService(): CommentModerationService {
        return app(CommentModerationService::class);
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
    protected function managePostsFieldVisibility(Request $request, $data): mixed {
        $data = $this->moderationFieldsVisibility($request, $data, ['moderation_info', 'reports_count']);
        $data = $this->filterExternalContent($request, $data);

        return $data;
    }

    /**
     * Manages visibility of fields in comment data based on user permissions and settings
     *
     * @param Request $request The current HTTP request
     * @param mixed $data The data to filter (can be Comment, Collection, or LengthAwarePaginator)
     * @return mixed The filtered data with appropriate field visibility
     */
    protected function manageCommentsFieldVisibility(Request $request, $data): mixed {
        $data = $this->moderationFieldsVisibility($request, $data, ['moderation_info', 'reports_count']);
        $data = $this->getCommentModerationService()->replaceReportedContent($data);
        return $data;
    }

    /**
     * Manages visibility of fields in user profile data based on user permissions and settings
     *
     * @param Request $request The current HTTP request
     * @param mixed $data The data to filter (can be UserProfile, Collection, or LengthAwarePaginator)
     * @return mixed The filtered data with appropriate field visibility
     */
    protected function manageUserProfileFieldVisibility(Request $request, $data): mixed {
        $data = $this->moderationFieldsVisibility($request, $data, ['reports_count']);
        return $data;
    }


    /**
     * Manage visibility of moderator fields based on user role
     * 
     * @param Request $request The current HTTP request
     * @param mixed $data The data to filter
     * @param array $fields Fields to make visible/hidden based on user role
     * @return mixed The filtered data
     */
    protected function moderationFieldsVisibility(Request $request, $data, array $fields = []): mixed {
        if (!empty($fields)) {
            $user = $this->getUserFromToken($request);
            if ($user->role === 'admin' || $user->role === 'moderator') {
                $data->makeVisible($fields);
            } else {
                $data->makeHidden($fields);
            }
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
    protected function filterExternalContent(Request $request, $data): mixed {
        $user = $this->getUserFromToken($request);

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
