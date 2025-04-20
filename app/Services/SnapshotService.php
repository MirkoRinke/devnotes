<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Comment;
use App\Models\UserProfile;

class SnapshotService {
    /**
     * Create a snapshot of the reportable entity
     *
     * @param mixed $reportable The reportable entity (Post, UserProfile, Comment)
     * @param string $reportableType The fully qualified class name of the reportable
     * @return array The snapshot of the reportable entity
     */
    public function createSnapshot($reportable, $reportableType): array|null {
        switch ($reportableType) {
            case UserProfile::class:
                return $this->userProfileSnapshot($reportable);
            case Post::class:
                return $this->postSnapshot($reportable);
            case Comment::class:
                return $this->commentSnapshot($reportable);
            default:
                return null;
        }
    }

    /**
     * Create a snapshot of the user profile
     *
     * @param UserProfile $userProfile The user profile entity
     * @return array The snapshot of the user profile
     */
    protected function userProfileSnapshot($userProfile): array {

        $user = $userProfile->user()->first(['name', 'email', 'role']);

        return [
            'user_id' => $userProfile->user_id,
            'display_name' => $userProfile->display_name,
            'public_email' => $userProfile->public_email,
            'website' => $userProfile->website,
            'location' => $userProfile->location,
            'biography' => $userProfile->biography,
            'skills' => $userProfile->skills,
            'social_links' => $userProfile->social_links,
            'contact_channels' => $userProfile->contact_channels,
            'user_data' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ]
        ];
    }

    /**
     * Create a snapshot of the post
     *
     * @param Post $post The post entity
     * @return array The snapshot of the post
     */
    protected function postSnapshot($post): array {
        return [
            'user_id' => $post->user_id,
            'title' => $post->title,
            'code' => $post->code,
            'description' => $post->description,
            'resources' => $post->resources,
            'language' => $post->language,
            'images' => $post->images,
            'category' => $post->category,
            'tags' => $post->tags
        ];
    }

    /**
     * Create a snapshot of the comment
     *
     * @param Comment $comment The comment entity
     * @return array The snapshot of the comment
     */
    protected function commentSnapshot($comment): array {
        return [
            'user_id' => $comment->user_id,
            'post_id' => $comment->post_id,
            'parent_id' => $comment->parent_id,
            'content' => $comment->content,
            'parent_content' => $comment->parent_content,
        ];
    }
}
