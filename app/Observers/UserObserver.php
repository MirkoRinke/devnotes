<?php

namespace App\Observers;

use App\Models\User;
use App\Models\UserProfile;
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
        //
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void {
        //
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
