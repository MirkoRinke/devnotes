<?php

namespace App\Policies;

use App\Models\Like;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LikePolicy {
    /**
     * The user can only delete their own like.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Like  $commentLike
     * @return bool
     */
    public function delete(User $user, Like $commentLike): bool {
        return $user->id === $commentLike->user_id;
    }
}
