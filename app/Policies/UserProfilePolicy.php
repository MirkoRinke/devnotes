<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserProfile;

use App\Traits\PolicyChecks;

/**
 * UserProfilePolicy
 * 
 * This policy class is responsible for authorizing actions on the UserProfile model.
 * It checks if the user is an admin or the owner of the profile to determine access.
 */
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

        if ($userProfile->is_public) {
            return true;
        }

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
