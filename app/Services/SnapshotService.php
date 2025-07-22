<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Comment;
use App\Models\UserProfile;

/**
 * This Class SnapshotService is responsible for creating snapshots of reportable entities such as Post, UserProfile, and Comment.
 * It provides methods to create snapshots of these entities, including user data if requested.
 */
class SnapshotService {
    /**
     * Create a snapshot of the reportable entity
     *
     * @param mixed $reportable The reportable entity (Post, UserProfile, Comment)
     * @param string $reportableType The fully qualified class name of the reportable
     * @return array The snapshot of the reportable entity
     * 
     * @example | $reportableSnapshot = $this->snapshotService->createSnapshot($reportable, $reportableType);
     */
    public function createSnapshot($reportable, $reportableType): array|null {
        switch ($reportableType) {
            case UserProfile::class:
                return $this->userProfileSnapshot($reportable, true);
            case Post::class:
                return $this->postSnapshot($reportable, true);
            case Comment::class:
                return $this->commentSnapshot($reportable, true);
            default:
                return null;
        }
    }

    /**
     * Create a snapshot of the user profile
     *
     * @param UserProfile $userProfile The user profile entity
     * @return array The snapshot of the user profile ( user data included if requested )
     * 
     * @example | $this->userProfileSnapshot($reportable, true);
     */
    protected function userProfileSnapshot($userProfile, $user_data = false): array {
        $userProfile_data = [
            'user_id' => $userProfile->user_id,
            'display_name' => $userProfile->display_name,
            'public_email' => $userProfile->public_email,
            'website' => $userProfile->website,
            'location' => $userProfile->location,
            'biography' => $userProfile->biography,
            'skills' => $userProfile->skills,
            'social_links' => $userProfile->social_links,
            'contact_channels' => $userProfile->contact_channels,
        ];

        if ($user_data) {
            $user = $userProfile->user()->first(['name', 'email', 'role']);
            $user_data = [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ];
            return array_merge($userProfile_data, ['user_data' => $user_data]);
        }

        // If user data is not needed, return the user profile data only
        return $userProfile_data;
    }

    /**
     * Create a snapshot of the post
     *
     * @param Post $post The post entity
     * @return array The snapshot of the post ( user data included if requested )
     * 
     * @example | $this->postSnapshot($reportable, true);
     */
    public function postSnapshot($post, $user_data = false): array {
        $technologies = [];
        foreach ($post->technologies as $technology) {
            $technologies[] = [
                'id' => $technology->id,
                'name' => $technology->name,
            ];
        }

        $languages = [];
        foreach ($post->languages as $language) {
            $languages[] = [
                'id' => $language->id,
                'name' => $language->name,
            ];
        }

        $tags = [];
        foreach ($post->tags as $tag) {
            $tags[] = [
                'id' => $tag->id,
                'name' => $tag->name,
            ];
        }

        $post_data = [
            'user_id' => $post->user_id,
            'title' => $post->title,
            'code' => $post->code,
            'description' => $post->description,
            'images' => $post->images,
            'videos' => $post->videos,
            'resources' => $post->resources,
            'external_source_previews' => $post->external_source_previews,
            'category' => $post->category,
            'post_type' => $post->post_type,
            'status' => $post->status,
            'created_at' => $post->created_at,
            'updated_at' => $post->updated_at,
            'tags' => $tags,
            'languages' => $languages,
            'technologies' => $technologies,
        ];

        if ($user_data) {
            $user = $post->user()->first(['name', 'email', 'role']);
            $user_data = [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ];
            return array_merge($post_data, ['user_data' => $user_data]);
        }

        return $post_data;
    }

    /**
     * Create a snapshot of the comment
     *
     * @param Comment $comment The comment entity
     * @return array The snapshot of the comment ( user data included if requested )
     * 
     * @example | $this->commentSnapshot($reportable, true);
     */
    protected function commentSnapshot($comment, $user_data = false): array {
        $comment_data = [
            'user_id' => $comment->user_id,
            'post_id' => $comment->post_id,
            'parent_id' => $comment->parent_id,
            'content' => $comment->content,
            'parent_content' => $comment->parent_content,
        ];

        if ($user_data) {
            $user = $comment->user()->first(['name', 'email', 'role']);
            $user_data = [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ];
            return array_merge($comment_data, ['user_data' => $user_data]);
        }

        return $comment_data;
    }
}
