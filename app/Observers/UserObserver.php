<?php

namespace App\Observers;

use App\Models\Like;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserReport;
use App\Services\ModerationService;

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

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void {
        // Delete all reports where this user is the reportable entity
        UserReport::where('reportable_type', User::class)
            ->where('reportable_id', $user->id)
            ->delete();

        // Delete all likes where this user is the likeable entity
        Like::where('likeable_type', User::class)
            ->where('likeable_id', $user->id)
            ->delete();
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
