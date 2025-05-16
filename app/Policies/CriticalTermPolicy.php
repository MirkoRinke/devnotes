<?php

namespace App\Policies;

use App\Models\User;

use App\Traits\PolicyChecks;

/**
 * Class CriticalTermPolicy
 * 
 * This policy class handles authorization for the CriticalTerm model.
 * It uses the PolicyChecks trait for common checks.
 */
class CriticalTermPolicy {

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
     * @example | $this->authorize('viewAny', CriticalTerm::class);
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
     * @example | $this->authorize('view', $criticalTerm);
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
     * @example | $this->authorize('create', CriticalTerm::class);
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
     * @example | $this->authorize('update', $criticalTerm);
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
     * @example | $this->authorize('delete', $criticalTerm);
     */
    public function delete(User $user): bool {
        return $this->hasModeratorPrivileges($user);
    }
}
