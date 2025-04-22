<?php

namespace App\Policies;

use App\Models\CriticalTerm;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CriticalTermPolicy {
    /**
     * Determine whether the user can access the admin area.
     */
    private function accessAdminArea(User $user): bool {
        return $user->role === 'admin' || $user->role === 'moderator';
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool {
        return $this->accessAdminArea($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user): bool {
        return $this->accessAdminArea($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool {
        return $this->accessAdminArea($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user): bool {
        return $this->accessAdminArea($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user): bool {
        return $this->accessAdminArea($user);
    }
}
