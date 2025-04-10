<?php

namespace App\Observers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\UserLike;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserReport;
use App\Services\UserModerationService;
use Illuminate\Support\Facades\DB;

/**
 * TODO: Implement failed_operations table and service to track and manage failed deletions.
 * TODO: This will:
 * TODO: - Log parent entity information (ID, type)
 * TODO: - Provide admin panel interface for retry operations
 * TODO: - Handle cleanup of orphaned child records
 */

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
        app(UserModerationService::class)->checkAndReportUsername($user);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void {
        // Check if name was changed (display_name is handled by UserProfileObserver)
        if ($user->wasChanged('name')) {
            app(UserModerationService::class)->checkAndReportUsername($user);
        }
    }

    public function deleting(User $user) {

        /**
         * Check if the user is not the system user (id 3)
         */
        if ($user->id !== 3) {

            /**
             * Set the user_id of all posts to the system user (id 3)
             */
            retry(3, function () use ($user) {
                Post::where('user_id', $user->id)
                    ->chunkById(50, function ($posts) {
                        DB::transaction(function () use ($posts) {
                            DB::table('posts')
                                ->whereIn('id', $posts->pluck('id'))
                                ->update(['user_id' => 3]);
                        });
                    });
            }, 100, function ($attempt) {
                return pow(2, $attempt - 1) * 100;
            });

            /**
             * Set the user_id of all comments to the system user (id 3)
             */
            retry(3, function () use ($user) {
                Comment::where('user_id', $user->id)
                    ->chunkById(50, function ($comments) {
                        DB::transaction(function () use ($comments) {
                            DB::table('comments')
                                ->whereIn('id', $comments->pluck('id'))
                                ->update(['user_id' => 3]);
                        });
                    });
            }, 100, function ($attempt) {
                return pow(2, $attempt - 1) * 100;
            });
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void {
        /**
         * This remove all the user reports 
         */
        retry(3, function () use ($user) {
            UserReport::where('reportable_type', User::class)
                ->where('reportable_id', $user->id)
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
         * This remove all the user likes 
         */
        retry(3, function () use ($user) {
            UserLike::where('likeable_type', User::class)
                ->where('likeable_id', $user->id)
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
