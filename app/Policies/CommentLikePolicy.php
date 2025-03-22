<?php

namespace App\Policies;

use App\Models\CommentLike;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CommentLikePolicy {
    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CommentLike $commentLike): bool {
        return $user->id === $commentLike->user_id;
    }
}
