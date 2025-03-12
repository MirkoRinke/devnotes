<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PostPolicy {
    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Post $post): bool {
        if ($user->role === 'admin') {
            // Admin can update any post
            return true;
        }

        // Only the post owner can update the post
        return $user->id === $post->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Post $post): bool {
        if ($user->role === 'admin') {
            // Admin can delete any post
            return true;
        }

        // Only the post owner can delete the post
        return $user->id === $post->user_id;
    }
}
