<?php

namespace App\Policies;

use App\Models\User;

use App\Traits\PolicyChecks;

/**
 * Policy for ForbiddenName model
 * 
 * Controls authorization for viewing, creating, updating and deleting forbidden names.
 * It uses the PolicyChecks trait for common checks.
 */
class ForbiddenNamePolicy {

    /**
     * The traits used in the policy
     */
    use PolicyChecks;

    /**
     * Determine whether the user can view any models.
     * 
     * @param User $user
     * @return bool
     * 
     * @example | $this->authorize('viewAny', ForbiddenName::class);
     */
    public function viewAny(User $user): bool {
        return $this->hasModeratorPrivileges($user);
    }

    /**
     * Determine whether the user can view the model.
     * 
     * @param User $user
     * @return bool
     * 
     * @example | $this->authorize('view', $forbiddenName);
     */
    public function view(User $user): bool {
        return $this->hasModeratorPrivileges($user);
    }

    /**
     * Determine whether the user can create models.
     * 
     * @param User $user
     * @return bool
     * 
     * @example | $this->authorize('create', ForbiddenName::class);
     */
    public function create(User $user): bool {
        return $this->hasModeratorPrivileges($user);
    }

    /**
     * Determine whether the user can update the model.
     * 
     * @param User $user
     * @return bool
     * 
     * @example | $this->authorize('update', $forbiddenName);
     */
    public function update(User $user): bool {
        return $this->hasModeratorPrivileges($user);
    }

    /**
     * Determine whether the user can delete the model.
     * 
     * @param User $user
     * @return bool
     * 
     * @example | $this->authorize('delete', $forbiddenName);
     */
    public function delete(User $user): bool {
        return $this->hasModeratorPrivileges($user);
    }
}
