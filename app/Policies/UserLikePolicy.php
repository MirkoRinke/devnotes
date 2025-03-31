<?php

namespace App\Policies;

use App\Models\UserLike;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserLikePolicy {

    /**
     * Determine whether the user can view any likes.
     */
    public function viewAny(User $user): bool {
        if ($user->role === 'admin') {
            // This user is an admin
            return true;
        }
        return false;
    }

    /**
     * The user can only delete their own like.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\UserLike  $commentLike
     * @return bool
     */
    public function delete(User $user, UserLike $commentLike): bool {
        return $user->id === $commentLike->user_id;
    }
}
