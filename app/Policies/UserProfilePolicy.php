<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserProfile;

use App\Traits\PolicyChecks;

class UserProfilePolicy {

    /**
     * The traits used in the policy
     */
    use PolicyChecks;

    /**
     * Determine whether the user can view the model.
     * 
     * @param User $user
     * @param UserProfile $userProfile
     * @return bool
     * 
     * @example | $this->authorize('view', $userProfile);
     */
    public function view(User $user, UserProfile $userProfile): bool {
        if ($this->isAdmin($user)) {
            return true;
        }
        // If the profile is public, everyone can view it
        if ($userProfile->is_public) {
            return true;
        }

        // If the profile is private, only the owner can view it
        return $this->isOwner($user, $userProfile);
    }

    /**
     * Determine whether the user can update the model.
     * 
     * @param User $user
     * @param UserProfile $userProfile
     * @return bool
     * 
     * @example | $this->authorize('update', $userProfile);
     */
    public function update(User $user, UserProfile $userProfile): bool {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->isOwner($user, $userProfile);
    }
}
