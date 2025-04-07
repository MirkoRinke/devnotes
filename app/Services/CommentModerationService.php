<?php

namespace App\Services;

use App\Models\Comment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CommentModerationService {

    /**
     * Replace the content of the comment with a message if it has been reported too many times
     * If the comment has children, replace the parent_content in the children with a message
     *
     * @param Comment|Collection|LengthAwarePaginator $comment
     * @return Comment|Collection|LengthAwarePaginator
     */
    function replaceReportedContent($comment) {
        if ($comment instanceof Collection || $comment instanceof LengthAwarePaginator) {
            foreach ($comment as $c) {
                $this->applyReportModeration($c);
            }
        } else {
            $this->applyReportModeration($comment);
        }
        return $comment;
    }


    /**
     * Apply report moderation to a comment
     * This method checks if the comment has been reported too many times
     * If so, it replaces the content with a message
     *
     * @param Comment $comment
     * @return Comment
     */
    function applyReportModeration($comment) {
        /**
         * Check if the comment has report attribute
         * If not, return the comment
         */
        if (!isset($comment->reports_count)) {
            return $comment;
        }

        /**
         * Check if the comment has been reported too many times
         */
        if ($comment->reports_count >= 5) {
            $comment->content = "This comment has been reported too many times and is no longer available";
        }

        /**
         * Check if the comment has a parent and if the parent has been reported too many times
         */
        if ($comment->parent_id !== null) {
            $parentComment = $comment->parent;
            if ($parentComment && $parentComment->reports_count >= 5) {
                $comment->parent_content = "This comment has been reported too many times and is no longer available";
            }
        }

        /**
         * Check if the comment has children and if the children have been reported too many times
         * If so, replace the parent_content in the children with a message and the content in the parent with a message
         */
        if ($comment->children && $comment->children->isNotEmpty()) {
            foreach ($comment->children as $child) {
                if ($comment->reports_count >= 5) {
                    $child->parent_content = "This comment has been reported too many times and is no longer available";
                    $child->parent->content = "This comment has been reported too many times and is no longer available";
                }
            }
        }
        return $comment;
    }
}
