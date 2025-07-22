<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

use App\Traits\PolicyChecks;

/**
 * Class PostPolicy
 * 
 * This policy class handles authorization for the Post model.
 * It uses the PolicyChecks trait for common checks.
 */
class PostPolicy {

    /**
     * The traits used in the policy
     */
    use PolicyChecks;

    /**
     * Determine whether the user can update the model.
     * 
     * @param User $user
     * @param Post $post
     * @return bool
     * 
     * @example | $this->authorize('update', $post);
     */
    public function update(User $user, Post $post): bool {
        if ($this->hasModeratorPrivileges($user)) {
            return true;
        }

        return $this->isOwner($user, $post);
    }

    /**
     * Determine whether the user can delete the model.
     * 
     * @param User $user
     * @param Post $post
     * @return bool
     * 
     * @example | $this->authorize('delete', $post);
     */
    public function delete(User $user, Post $post): bool {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->isOwner($user, $post);
    }
}
