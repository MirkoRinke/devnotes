<?php

namespace App\Services;

use App\Models\Post;

/**
 * The Class HistoryService is responsible for managing the history of posts.
 * It creates history entries for posts when they are updated.
 */
class HistoryService {

    /**
     * The services used in the controller
     */
    protected $snapshotService;

    /**
     * Constructor to initialize the services
     */
    public function __construct(SnapshotService $snapshotService) {
        $this->snapshotService = $snapshotService;
    }

    /**
     * Create a history entry for a post
     * 
     * @param Post $post The post to create history for
     * @param int $user_id The ID of the user creating the history entry
     * @return array The updated history log
     * 
     * @example | $this->historyService->createPostHistory($post, $user->id)
     */
    public function createPostHistory(Post $post, $user_id): array {
        // Check if the user is the owner of the post
        if ($post->user_id !== $user_id) {
            return $post->history ?? [];
        }

        // Only create a new history entry if the post was updated within the time threshold (2 hours)
        if ($post->updated_at > now()->subHours(2)) {
            return $post->history ?? [];
        }

        // Create snapshot of current state
        $newHistory = $this->snapshotService->postSnapshot($post);

        // Get existing history
        $historyLog = $post->history ?? [];

        // Normalize history structure
        if (!empty($historyLog) && !isset($historyLog[0])) {

            // If historyLog is not an array of arrays, wrap it in an array
            $historyLog = [$historyLog];
        }

        // Add metadata to history entry
        $newHistory = array_merge(
            $newHistory,
            [
                'created_at' => now(),
            ]
        );

        // Add the new history entry to the beginning of the history log
        array_unshift($historyLog, $newHistory);

        return $historyLog;
    }
}
