<?php

namespace App\Traits;

use App\Models\User;

/**
 * Trait PolicyChecks
 * 
 * This trait contains common policy checks for user roles and ownership.
 */
trait PolicyChecks {
    /**
     * Check if user has admin role
     * 
     * @param User $user
     * @return bool
     * 
     * @example | $this->isAdmin($user)
     */
    protected function isAdmin(User $user): bool {
        return $user->role === 'admin';
    }

    /**
     * Check if user has admin or moderator role
     * 
     * @param User $user
     * @return bool
     * 
     * @example | $this->hasModeratorPrivileges($user)
     */
    protected function hasModeratorPrivileges(User $user): bool {
        return $user->role === 'admin' || $user->role === 'system' || $user->role === 'moderator';
    }

    /**
     * Check if user owns the model
     * 
     * @param User $user
     * @param mixed $model Any model with user_id
     * @return bool
     * 
     * @example | $this->isOwner($user, $model)
     */
    protected function isOwner(User $user, $model): bool {
        return $user->id === $model->user_id;
    }

    /**
     * Check if user does NOT own the model
     * 
     * @param User $user
     * @param mixed $model Any model with user_id
     * @return bool
     * 
     * @example | $this->isNotOwner($user, $model)
     */
    protected function isNotOwner(User $user, $model): bool {
        return $user->id !== $model->user_id;
    }
}
