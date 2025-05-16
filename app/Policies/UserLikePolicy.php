<?php

namespace App\Policies;

use App\Models\UserLike;
use App\Models\User;

use App\Traits\PolicyChecks;

/**
 * Class UserLikePolicy
 * 
 * This policy class handles authorization for the UserLike model.
 * It uses the PolicyChecks trait for common checks.
 */
class UserLikePolicy {

    /**
     * The traits used in the policy
     */
    use PolicyChecks;

    /**
     * Determine whether the user can view any likes.
     * 
     * @param User $user
     * @return bool
     * 
     * @example | $this->authorize('viewAny', UserLike::class); 
     */
    public function viewAny(User $user): bool {
        if ($this->isAdmin($user)) {
            return true;
        }
        return false;
    }

    /**
     * The user can only delete their own like.
     *
     * @param User $user
     * @param UserLike $userLike
     * @return bool
     * 
     * @example | $this->authorize('delete', $userLike);
     */
    public function delete(User $user, UserLike $userLike): bool {
        return $this->isOwner($user, $userLike);
    }
}
