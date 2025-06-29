<?php

namespace App\Services;

use App\Models\Comment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * This Class CommentModerationService is responsible for moderating comments
 * It checks if the comment has been reported too many times and replaces the content with a message
 * It also checks if the comment has children and replaces the parent_content in the children with a message
 */
class CommentModerationService {

    /**
     * Replace the content of the comment with a message if it has been reported too many times
     * If the comment has children, replace the parent_content in the children with a message
     *
     * @param Comment|Collection|LengthAwarePaginator $comment
     * @return Comment|Collection|LengthAwarePaginator
     * 
     * @example | commentModerationService->replaceReportedContent($comment);
     */
    public function replaceReportedContent($comment) {
        if ($comment instanceof Collection || $comment instanceof LengthAwarePaginator) {
            foreach ($comment as $c) {
                $this->applyReportModeration($c);
            }
        } else if ($comment instanceof Comment) {
            $this->applyReportModeration($comment);
        }
        return $comment;
    }


    /**
     * Applies moderation logic to a comment and its loaded relations.
     *
     * - Replaces the "content" field if the comment has been reported too many times.
     * - Replaces the "parent_content" field if the parent has been reported too many times.
     * - Replaces the "parent_content" field in all children if this comment has been reported too many times.
     * - Replaces the "content" field in the parent object of a child if the parent has been reported too many times.
     * - Recursively applies moderation to all loaded children.
     *
     * @param Comment $comment The comment object (with loaded relations)
     * @return Comment The moderated comment object
     *
     * @example $this->applyReportModeration($comment);
     */
    private function applyReportModeration($comment) {
        if (!isset($comment->reports_count)) {
            throw new \RuntimeException('reports_count is missing! Check query setup.');
        }


        /**
         * Replace content if this comment has been reported too many times
         */
        if ($comment->reports_count >= 5 && array_key_exists('content', $comment->getAttributes())) {
            $comment->content = "This comment has been reported too many times and is no longer available";
        }

        /**
         * Replace parent_content if the parent has been reported too many times
         */
        if ($comment->parent_id !== null && $comment->relationLoaded('parent')) {
            $parentComment = $comment->parent;
            if ($parentComment && $parentComment->reports_count >= 5 && array_key_exists('parent_content', $comment->getAttributes())) {
                $comment->parent_content = "This comment has been reported too many times and is no longer available";
            }
        }

        /**
         * Replace parent_content if this comment has been reported too many times
         * Replace content in the parent object of the child if the parent has been reported too many times
         * 
         * Recursively apply moderation to the child
         */
        if ($comment->relationLoaded('children') && $comment->children && $comment->children->isNotEmpty()) {
            foreach ($comment->children as $child) {

                if ($comment->reports_count >= 5 && array_key_exists('parent_content', $child->getAttributes())) {
                    $child->parent_content = "This comment has been reported too many times and is no longer available";
                }

                if ($child->relationLoaded('parent') && $child->parent && $comment->reports_count >= 5 && array_key_exists('content', $child->parent->getAttributes())) {
                    $child->parent->content = "This comment has been reported too many times and is no longer available";
                }

                $this->applyReportModeration($child);
            }
        }
        return $comment;
    }
}
