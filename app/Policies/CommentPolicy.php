<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

use App\Traits\PolicyChecks;

/**
 * Class CommentPolicy
 * 
 * This policy class handles authorization for the Comment model.
 * It uses the PolicyChecks trait for common checks.
 */
class CommentPolicy {

    /**
     * The traits used in the policy
     */
    use PolicyChecks;

    /**
     * Determine whether the user can create models.
     * 
     * @param User $user
     * @return bool
     * 
     * @example | $this->authorize('create', Comment::class);
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
     * 
     * @param User $user
     * @param Comment $comment
     * @return bool
     * 
     * @example | $this->authorize('update', $comment);
     */
    public function update(User $user, Comment $comment): bool {
        if ($this->hasModeratorPrivileges($user)) {
            return true;
        }

        if ($this->isNotOwner($user, $comment)) {
            return false;
        }

        return now()->diffInMinutes($comment->created_at) <= 15;
    }

    /**
     * Determine whether the user can delete the comment.
     * 
     * @param User $user
     * @param Comment $comment
     * @return bool
     * 
     * @example | $this->authorize('delete', $comment);
     */
    public function delete(User $user): bool {
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can delete the comment.
     * 
     * @param User $user
     * @param Comment $comment
     * @return bool
     * 
     * @example | $this->authorize('deleteComment', $comment);
     */
    public function deleteComment(User $user, Comment $comment): bool {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->isOwner($user, $comment);
    }
}
