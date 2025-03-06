<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserFavorite;
use Illuminate\Auth\Access\HandlesAuthorization;

// Laravel find the policy by the name of the model and the word "Policy" at the end.
class UserFavoritePolicy {
    use HandlesAuthorization;

    /**
     * The user can only delete their own favorite.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\UserFavorite  $userFavorite
     * @return mixed
     */
    public function delete(User $user, UserFavorite $userFavorite) {
        return $user->id === $userFavorite->user_id;
    }
}
