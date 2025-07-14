<?php

namespace App\Policies;

use App\Models\User;

use App\Traits\PolicyChecks;

/**
 * Class UserPolicy
 * 
 * This policy class handles authorization for the User model.
 * It uses the PolicyChecks trait for common checks.
 */
class UserPolicy {

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
     * @example | $this->authorize('viewAny', User::class);
     */
    public function viewAny(User $user): bool {
        if ($this->isAdmin($user)) {
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can view the model.
     * 
     * @param User $user
     * @param User $model
     * @return bool
     * 
     * @example | $this->authorize('view', $model);
     */
    public function view(User $user, User $model): bool {
        if ($this->isAdmin($user)) {
            return true;
        }
        return $this->isSameUser($user, $model);
    }

    /**
     * Determine whether the user can update the model.
     * 
     * @param User $user
     * @param User $model
     * @return bool
     * 
     * @example | $this->authorize('update', $model);
     */
    public function update(User $user, User $model): bool {
        if ($this->isAdmin($user)) {
            return true;
        }
        return $this->isSameUser($user, $model);
    }

    /**
     * Determine whether the user can delete the model.
     * 
     * @param User $user
     * @param User $model
     * @return bool
     * 
     * @example | $this->authorize('delete', $model);
     */
    public function delete(User $user, User $model): bool {
        // Protect against deleting admin, system, or moderator accounts
        if ($model->role === 'admin' || $model->role === 'system' || $model->role === 'moderator') {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        // Guests cannot delete their own accounts
        if ($user->account_purpose === 'guest') {
            return false;
        }

        return $this->isSameUser($user, $model);
    }

    /**
     * Determine whether the user can banUser the model.
     * 
     * @param User $user
     * @param User $model
     * @return bool
     * 
     * @example | $this->authorize('banUser', $model);
     */
    public function banUser(User $user, User $model): bool {
        if ($this->isAdmin($user) && $model->role !== 'admin') {
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can unbanUser the model.
     * 
     * @param User $user
     * @param User $model
     * @return bool
     * 
     * @example | $this->authorize('unbanUser', $model);
     */
    public function unbanUser(User $user): bool {
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can getBanned the model.
     * 
     * @param User $user
     * @param User $model
     * @return bool
     * 
     * @example | $this->authorize('getBanned', $model);
     */
    public function getBanned(User $user): bool {
        return $this->isAdmin($user);
    }
}
