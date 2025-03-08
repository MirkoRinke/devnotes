<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PostPolicy {
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(): bool {
        // Every user can view all posts
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(): bool {
        // Every user can view a post
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool {
        // Only authenticated users can create a post
        return true;
    }

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
