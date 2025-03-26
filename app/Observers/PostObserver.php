<?php

namespace App\Observers;

use App\Models\Post;
use App\Models\UserReport;

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
        // Delete all reports where this post is the reportable entity
        UserReport::where('reportable_type', Post::class)
            ->where('reportable_id', $post->id)
            ->delete();
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
