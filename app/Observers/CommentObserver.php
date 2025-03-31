<?php

namespace App\Observers;

use App\Models\Comment;
use App\Models\Like;
use App\Models\UserReport;

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
        $comment->children()->each(function ($child) {
            $child->delete(); // This triggers CommentObserver::deleted for each child
        });
    }

    /**
     * Handle the Comment "deleted" event.
     */
    public function deleted(Comment $comment): void {
        // Delete all reports where this comment is the reportable entity
        UserReport::where('reportable_type', Comment::class)
            ->where('reportable_id', $comment->id)
            ->delete();

        // Delete all likes where this comment is the likeable entity
        Like::where('likeable_type', Comment::class)
            ->where('likeable_id', $comment->id)
            ->delete();
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
