<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Carbon;

class HistoryService {
    protected $snapshotService;

    public function __construct(SnapshotService $snapshotService) {
        $this->snapshotService = $snapshotService;
    }

    /**
     * Create a history entry for a post
     * 
     * @param Post $post The post to create history for
     * @param int $user_id The ID of the user creating the history entry
     * @return array The updated history log
     */
    public function createPostHistory(Post $post, $user_id): array {
        // Check if the user is the owner of the post
        if ($post->user_id !== $user_id) {
            return $post->history ?? [];
        }

        // Only create a new history entry if the post was updated within the time threshold
        if ($post->updated_at > now()->subHours(2)) {
            return $post->history ?? [];
        }

        // Create snapshot of current state
        $newHistory = $this->snapshotService->postSnapshot($post);

        // Get existing history
        $historyLog = $post->history ?? [];

        // Normalize history structure
        if (!empty($historyLog) && !isset($historyLog[0])) {
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
