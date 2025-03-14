<?php

namespace App\Policies;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ApiKeyPolicy {
    /**
     * Helper function to check if the user is an admin
     */
    private function isAdmin(User $user): bool {
        return $user->role === 'admin';
    }


    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool {
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool {
        return $this->isAdmin($user);
    }


    /**
     * Determine whether the user can toggle the status of the model.
     */
    public function toggleStatus(User $user): bool {
        return $this->isAdmin($user);
    }


    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user): bool {
        return $this->isAdmin($user);
    }
}
