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
        if ($post->user_id !== $user_id) {
            return $post->history ?? [];
        }

        /**
         * Only create a new history entry if the post was updated within the time threshold (2 hours)
         */
        if ($post->updated_at > now()->subHours(2)) {
            return $post->history ?? [];
        }

        $newHistory = $this->snapshotService->postSnapshot($post);

        $historyLog = $post->history ?? [];

        if (!empty($historyLog) && !isset($historyLog[0])) {
            /**
             * If historyLog is not an array of arrays, wrap it in an array
             */
            $historyLog = [$historyLog];
        }

        $newHistory = array_merge(
            $newHistory,
            [
                'history_created_at' => now(),
            ]
        );

        array_unshift($historyLog, $newHistory);

        return $historyLog;
    }
}
