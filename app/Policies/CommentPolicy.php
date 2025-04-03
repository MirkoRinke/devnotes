<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CommentPolicy {
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool {
        if ($user) {
            return true;
        }
        return false;
    }

    /**
     * Determine whether the user can update the model.
     * 
     * Admins can update any comment.
     * Users can only update their own comments within 15 minutes after creation.
     */
    public function update(User $user, Comment $comment): bool {
        // Check if user is an admin or moderator
        if ($user->role === 'admin' || $user->role === 'moderator') {
            return true;
        }
        // Check if user is the owner of the comment
        if ($user->id !== $comment->user_id) {
            return false;
        }
        // Check if the comment was created within the last 15 minutes
        return now()->diffInMinutes($comment->created_at) <= 15;
    }

    /**
     * Determine whether the user can view the comment.
     */
    public function delete(User $user, Comment $comment): bool {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can delete the comment.
     */
    public function deleteComment(User $user, Comment $comment): bool {
        if ($user->role === 'admin') {
            return true;
        }
        return $user->id === $comment->user_id;
    }
}
