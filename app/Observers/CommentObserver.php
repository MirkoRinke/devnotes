<?php

namespace App\Observers;

use App\Models\Comment;
use App\Models\User;
use App\Models\Post;
use App\Models\UserLike;
use App\Models\UserReport;

use Illuminate\Support\Facades\DB;

/**
 * TODO: Implement failed_operations table and service to track and manage failed deletions.
 * TODO: This will:
 * TODO: - Log parent entity information (ID, type)
 * TODO: - Provide admin panel interface for retry operations
 * TODO: - Handle cleanup of orphaned child records
 */

class CommentObserver {
    /**
     * Update the last_comment_at timestamp of the parent post
     *
     * @param Comment $comment
     * @return void
     */
    private function updateLastCommentAt(Comment $comment): void {
        retry(3, function () use ($comment) {
            Post::where('id', $comment->post_id)
                ->update(['last_comment_at' => now()]);
        }, 100, function ($attempt) {
            return pow(2, $attempt - 1) * 100;
        });
    }

    /**
     * Update the comments_count in the parent post
     *
     * @param Comment $comment
     * @param string $operation Operation to perform ('increment' or 'decrement')
     * @return void
     */
    private function updateCommentsCount(Comment $comment, string $operation = 'increment'): void {
        retry(3, function () use ($comment, $operation) {
            Post::where('id', $comment->post_id)
                ->$operation('comments_count', 1);
        }, 100, function ($attempt) {
            return pow(2, $attempt - 1) * 100;
        });
    }

    /**
     * Handle the Comment "created" event.
     */
    public function created(Comment $comment): void {
        // Update last_comment_at
        $this->updateLastCommentAt($comment);

        // Update comments_count
        $this->updateCommentsCount($comment, 'increment');
    }

    /**
     * Handle the Comment "updated" event.
     */
    public function updated(Comment $comment): void {
        // Update last_comment_at
        $this->updateLastCommentAt($comment);
    }

    /**
     * Handle the Comment "deleting" event.
     * This event is triggered when the model is being deleted.
     * This is a good place to delete all child comments.
     */
    public function deleting(Comment $comment): void {
        /**
         * Delete all child comments associated with the comment
         */
        retry(3, function () use ($comment) {
            $comment->children()->chunkById(50, function ($children) {
                DB::transaction(function () use ($children) {
                    foreach ($children as $child) {
                        $child->delete(); // This triggers CommentObserver::deleted for each child
                    }
                });
            });
        }, 100, function ($attempt) {
            return pow(2, $attempt - 1) * 100;
        });
    }

    /**
     * Handle the Comment "deleted" event.
     */
    public function deleted(Comment $comment): void {
        // Update last_comment_at
        $this->updateLastCommentAt($comment);

        // Update comments_count
        $this->updateCommentsCount($comment, 'decrement');

        /**
         * Delete all reports associated with the comment
         */
        retry(3, function () use ($comment) {
            UserReport::where('reportable_type', Comment::class)
                ->where('reportable_id', $comment->id)
                ->chunkById(50, function ($reports) {
                    DB::transaction(function () use ($reports) {
                        DB::table('user_reports')
                            ->whereIn('id', $reports->pluck('id'))
                            ->delete();
                    });
                });
        }, 100, function ($attempt) {
            return pow(2, $attempt - 1) * 100;
        });

        /**
         * Delete all likes associated with the comment
         */
        retry(3, function () use ($comment) {
            UserLike::where('likeable_type', Comment::class)
                ->where('likeable_id', $comment->id)
                ->chunkById(50, function ($likes) {
                    DB::transaction(function () use ($likes) {
                        DB::table('user_likes')
                            ->whereIn('id', $likes->pluck('id'))
                            ->delete();
                    });
                });
        }, 100, function ($attempt) {
            return pow(2, $attempt - 1) * 100;
        });
    }

    /**
     * Handle the Comment "restored" event.
     */
    public function restored(Comment $comment): void {
        //
    }

    /**
     * Handle the Comment "force deleted" event.
     */
    public function forceDeleted(Comment $comment): void {
        //
    }
}
