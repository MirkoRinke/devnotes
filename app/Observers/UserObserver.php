<?php

namespace App\Observers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\UserLike;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserReport;
use App\Services\ModerationService;
use Illuminate\Support\Facades\DB;

class UserObserver {
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void {
        UserProfile::create([
            'user_id' => $user->id,
            'display_name' => $user->display_name,
        ]);

        // Check name for partially forbidden words
        app(ModerationService::class)->checkAndReportUsername($user);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void {
        // Check if name was changed (display_name is handled by UserProfileObserver)
        if ($user->wasChanged('name')) {
            app(ModerationService::class)->checkAndReportUsername($user);
        }
    }

    public function deleting(User $user) {
        // Check if the user is not the system user
        if ($user->id !== 2) {
            // Set the user_id of all posts to the system user (id 2)
            Post::where('user_id', $user->id)
                ->chunkById(50, function ($posts) {
                    DB::transaction(function () use ($posts) {
                        DB::table('posts')
                            ->whereIn('id', $posts->pluck('id'))
                            ->update(['user_id' => 2]);
                    });
                });

            Comment::where('user_id', $user->id)
                ->chunkById(50, function ($comments) {
                    DB::transaction(function () use ($comments) {
                        DB::table('comments')
                            ->whereIn('id', $comments->pluck('id'))
                            ->update(['user_id' => 2]);
                    });
                });
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void {
        // Delete reports in chunks
        UserReport::where('reportable_type', User::class)
            ->where('reportable_id', $user->id)
            ->chunkById(50, function ($reports) {
                DB::transaction(function () use ($reports) {
                    DB::table('user_reports')
                        ->whereIn('id', $reports->pluck('id'))
                        ->delete();
                });
            });

        // Delete likes in chunks
        UserLike::where('likeable_type', User::class)
            ->where('likeable_id', $user->id)
            ->chunkById(50, function ($likes) {
                DB::transaction(function () use ($likes) {
                    DB::table('user_likes')
                        ->whereIn('id', $likes->pluck('id'))
                        ->delete();
                });
            });
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void {
        //
    }
}
