<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Auth\Access\Response;

class UserProfilePolicy {

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, UserProfile $userProfile): bool {
        if ($user->role === 'admin') {
            // Admins can view any profile
            return true;
        }
        // If the profile is public, everyone can view it
        if ($userProfile->is_public) {
            return true;
        }

        // If the profile is private, only the owner can view it
        return $user->id === $userProfile->user_id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, UserProfile $userProfile): bool {
        if ($user->role === 'admin') {
            // Admins can update any profile
            return true;
        }
        // Only the owner can update the profile
        return $user->id === $userProfile->user_id;
    }
}
