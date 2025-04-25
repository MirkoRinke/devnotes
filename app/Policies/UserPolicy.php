<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy {
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool {
        if ($user->role === 'admin') {
            // This user is an admin
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool {
        if ($user->role === 'admin') {
            // This user is an admin
            return true;
        }
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool {
        if ($user->role === 'admin') {
            // This user is an admin
            return true;
        }
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool {
        // Protect against deleting admin, system, or moderator accounts
        if ($model->role === 'admin' || $model->role === 'system' || $model->role === 'moderator') {
            return false;
        }

        // This user is an admin
        if ($user->role === 'admin') {
            return true;
        }

        // Guests cannot delete their own accounts
        if ($user->account_purpose === 'guest') {
            return false;
        }

        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can banUser the model.
     */
    public function banUser(User $user, User $model): bool {
        if ($user->role === 'admin' && $model->role !== 'admin') {
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can unbanUser the model.
     */
    public function unbanUser(User $user): bool {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can getBanned the model.
     */
    public function getBanned(User $user): bool {
        return $user->role === 'admin';
    }
}
