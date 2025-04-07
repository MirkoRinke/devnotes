<?php

namespace App\Observers;

use App\Models\UserProfile;
use App\Services\UserModerationService;

class UserProfileObserver {
    /**
     * Handle the UserProfile "created" event.
     */
    public function created(UserProfile $profile): void {
        // 
    }

    /**
     * Handle the UserProfile "updated" event.
     */
    public function updated(UserProfile $profile): void {
        // Check if the display name has changed
        if ($profile->wasChanged('display_name')) {
            // Update the user's display name
            $profile->user()->update([
                'display_name' => $profile->display_name
            ]);

            // Check name for partially forbidden words
            app(UserModerationService::class)->checkAndReportUsername($profile->user);
        }
    }

    /**
     * Handle the UserProfile "deleted" event.
     */
    public function deleted(UserProfile $profile): void {
        //
    }

    /**
     * Handle the UserProfile "restored" event.
     */
    public function restored(UserProfile $profile): void {
        //
    }

    /**
     * Handle the UserProfile "force deleted" event.
     */
    public function forceDeleted(UserProfile $profile): void {
        //
    }
}
