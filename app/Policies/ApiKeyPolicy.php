<?php

namespace App\Policies;

use App\Models\User;

use App\Traits\PolicyChecks;

/**
 * Class ApiKeyPolicy
 * 
 * This policy class handles authorization for the ApiKey model.
 * It uses the PolicyChecks trait for common checks.
 */
class ApiKeyPolicy {

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
     * @example | $this->authorize('viewAny', ApiKey::class);
     */
    public function viewAny(User $user): bool {
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can create models.
     * 
     * @param User $user
     * @return bool
     * 
     * @example | $this->authorize('create', ApiKey::class);
     */
    public function create(User $user): bool {
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can toggle the status of the model.
     * 
     * @param User $user
     * @return bool
     * 
     * @example | $this->authorize('toggleStatus', ApiKey::class);
     */
    public function toggleStatus(User $user): bool {
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can delete the model.
     * 
     * @param User $user
     * @return bool
     * 
     * @example | $this->authorize('delete', ApiKey::class);
     */
    public function delete(User $user): bool {
        return $this->isAdmin($user);
    }
}
