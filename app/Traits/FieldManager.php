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
 * This FieldManager Trait Provides methods to manage field visibility and content filtering based on user permissions.
 * This trait handles access control for sensitive fields like moderation information and
 * external content based on user role and settings.
 * 
 * @requires \App\Traits\AuthHelper for getAuthenticatedUser method in the controller
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
     * Manages visibility of fields in post data based on user permissions and settings
     *
     * @param Request $request The current HTTP request
     * @param mixed $data The data to filter (can be Post, Collection, or LengthAwarePaginator)
     * @return mixed The filtered data with appropriate field visibility
     * 
     * @example | $query = $this->managePostsFieldVisibility($request, $query);
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
     * 
     * @example | $query = $this->manageCommentsFieldVisibility($request, $query);
     */
    protected function manageCommentsFieldVisibility(Request $request, $data): mixed {
        $data = $this->moderationFieldsVisibility($request, $data, ['moderation_info', 'reports_count']);
        $data = $this->getCommentModerationService()->replaceReportedContent($data);
        return $data;
    }

    /**
     * Manages visibility of fields in user data based on user permissions and settings
     *
     * @param Request $request The current HTTP request
     * @param mixed $data The data to filter (can be User, Collection, or LengthAwarePaginator)
     * @return mixed The filtered data with appropriate field visibility
     * 
     * @example | $query = $this->manageUsersFieldVisibility($request, $query);
     */
    protected function manageUsersFieldVisibility(Request $request, $data): mixed {
        $data = $this->moderationFieldsVisibility($request, $data, ['is_banned', 'was_ever_banned', 'moderation_info']);
        return $data;
    }


    /**
     * Manages visibility of fields in user profile data based on user permissions and settings
     *
     * @param Request $request The current HTTP request
     * @param mixed $data The data to filter (can be UserProfile, Collection, or LengthAwarePaginator)
     * @return mixed The filtered data with appropriate field visibility
     * 
     * @example | $query = $this->manageUserProfilesFieldVisibility($request, $query);
     */
    protected function manageUserProfilesFieldVisibility(Request $request, $data): mixed {
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
     * 
     * @example | $query = $this->moderationFieldsVisibility($request, $query, ['moderation_info', 'reports_count']);
     */
    protected function moderationFieldsVisibility(Request $request, $data, array $fields = []): mixed {
        if (!empty($fields)) {
            $user = $this->getAuthenticatedUser($request);
            if ($user && ($user->role === 'admin' || $user->role === 'moderator')) {
                $data->makeVisible($fields);
            } else {
                $data->makeHidden($fields);
            }
        }

        $data = $this->moderationFieldsVisibilityRelation($request, $data);

        return $data;
    }

    /**
     * Manage visibility of moderator fields in related models
     * 
     * @param Request $request The current HTTP request
     * @param mixed $data The data to filter (can be Post, Collection, or LengthAwarePaginator)
     * @return mixed The filtered data with appropriate field visibility
     * 
     * @example | $data = $this->moderationFieldsVisibilityRelation($request, $data);
     */
    protected function moderationFieldsVisibilityRelation(Request $request, $data): mixed {
        $user = $this->getAuthenticatedUser($request);
        $hasModeratorAccess = $user && ($user->role === 'admin' || $user->role === 'moderator');

        $fieldMap = [
            'user' => ['is_banned', 'was_ever_banned', 'moderation_info'],
            'follower' => ['is_banned', 'was_ever_banned', 'moderation_info'],
            'post' => ['moderation_info', 'reports_count'],
            'comment' => ['moderation_info', 'reports_count'],
            'parent' => ['moderation_info', 'reports_count'],
            'children' => ['moderation_info', 'reports_count'],
            'profile' => ['reports_count']
        ];

        // Define a closure to process each item
        $processItem = function ($item) use ($hasModeratorAccess, $fieldMap) {
            foreach ($fieldMap as $relation => $fields) {
                if ($item->relationLoaded($relation) && $item->{$relation}) {
                    // Set visibility based on user role
                    if ($hasModeratorAccess) {
                        $item->{$relation}->makeVisible($fields);
                    } else {
                        $item->{$relation}->makeHidden($fields);
                    }
                }
            }
        };

        // Process the data based on its type (Collection, LengthAwarePaginator, or single item)
        if ($data instanceof Collection || $data instanceof LengthAwarePaginator) {
            foreach ($data as $item) {
                $processItem($item);
            }
        } else {
            $processItem($data);
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
     * 
     * @example | $data = $this->filterExternalContent($request, $data);
     */
    protected function filterExternalContent(Request $request, $data): mixed {
        $user = $this->getAuthenticatedUser($request);

        $types = ['images', 'videos', 'resources'];

        $selectedFields = $this->getSelectFields($request);

        foreach ($types as $type) {
            if (!$selectedFields || in_array($type, $selectedFields)) {
                if (!$this->getExternalSourceService()->shouldDisplayExternals($request, $user, $type)) {
                    if ($data instanceof Collection || $data instanceof LengthAwarePaginator) {
                        foreach ($data as $post) {
                            if (!$user || ($post->user_id !== $user->id)) {
                                $post->{$type} = [];
                            }
                        }
                    } else if ($data instanceof Post) {
                        if (!$user || ($data->user_id !== $user->id)) {
                            $data->{$type} = [];
                        }
                    }
                }
            }
        }
        return $data;
    }
}
