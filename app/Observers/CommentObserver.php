<?php

namespace App\Observers;

use App\Models\Comment;
use App\Models\User;
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
     * Handle the Comment "created" event.
     */
    public function created(Comment $comment): void {
        //
    }

    /**
     * Handle the Comment "updated" event.
     */
    public function updated(Comment $comment): void {
        //
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
