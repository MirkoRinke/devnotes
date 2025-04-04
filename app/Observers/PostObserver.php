<?php

namespace App\Observers;

use App\Models\UserLike;
use App\Models\Post;
use App\Models\UserReport;

use Illuminate\Support\Facades\DB;

/**
 * TODO: Implement failed_operations table and service to track and manage failed deletions.
 * TODO: This will:
 * TODO: - Log parent entity information (ID, type)
 * TODO: - Provide admin panel interface for retry operations
 * TODO: - Handle cleanup of orphaned child records
 */

class PostObserver {
    /**
     * Handle the Post "created" event.
     */
    public function created(Post $post): void {
        //
    }

    /**
     * Handle the Post "updated" event.
     */
    public function updated(Post $post): void {
        //
    }

    /**
     * Handle the Post "deleted" event.
     */
    public function deleted(Post $post): void {

        /**
         * Delete all reports associated with the post
         */
        retry(3, function () use ($post) {
            UserReport::where('reportable_type', Post::class)
                ->where('reportable_id', $post->id)
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
         * Delete all likes associated with the post
         */
        retry(3, function () use ($post) {
            UserLike::where('likeable_type', Post::class)
                ->where('likeable_id', $post->id)
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
     * Handle the Post "restored" event.
     */
    public function restored(Post $post): void {
        //
    }

    /**
     * Handle the Post "force deleted" event.
     */
    public function forceDeleted(Post $post): void {
        //
    }
}
