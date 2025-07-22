<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

use App\Models\Comment;
use App\Models\Post;
use App\Models\UserLike;
use App\Models\UserReport;

/**
 * Class CommentRelationService
 * 
 * Handles operations related to comment relations, such as updating timestamps,
 * deleting child comments, and deleting likes and reports.
 */
class CommentRelationService {
    /**
     * Update the comments_updated_at timestamp of the parent post
     *
     * @param Comment $comment
     * @return void
     * 
     * @example | $this->commentRelationService->updateLastCommentAt($comment);
     */
    public function updateLastCommentAt(Comment $comment): void {
        Post::where('id', $comment->post_id)
            ->update(['comments_updated_at' => now()]);
    }

    /**
     * Update the comments_count in the parent post
     *
     * @param Comment $comment
     * @param string $operation Operation to perform ('increment' or 'decrement')
     * @return void
     * 
     * @example | $this->commentRelationService->updateCommentsCount($comment, 'increment');
     */
    public function updateCommentsCount(Comment $comment, string $operation): void {
        Post::where('id', $comment->post_id)
            ->$operation('comments_count', 1);
    }

    /**
     * Delete all child comments associated with the comment
     *
     * @param Comment $comment The parent comment
     * @return int Number of deleted comments
     * 
     * @example | $this->commentRelationService->deleteChildren($comment);
     */
    public function deleteChildren(Comment $comment): int {
        $childrenIds = $this->collectAllChildrenIds($comment) ?? [];

        if (empty($childrenIds)) {
            return 0;
        }

        $totalDeleted = 0;
        foreach (array_chunk($childrenIds, 100) as $chunk) {
            $this->deleteLikes($chunk);
            $this->deleteReports($chunk);
            $totalDeleted += Comment::whereIn('id', $chunk)->delete();
        }

        if ($totalDeleted > 0) {
            Post::where('id', $comment->post_id)->decrement('comments_count', $totalDeleted);
        }

        return $totalDeleted;
    }

    /**
     * Recursively collect all children IDs
     *
     * @param Comment $comment
     * @return array Array of child comment IDs including all descendants
     * 
     * @example | $this->collectAllChildrenIds($comment);
     */
    private function collectAllChildrenIds(Comment $comment): array {
        $ids = [];

        $children = $comment->children()->get(['id']);

        foreach ($children as $child) {
            $ids[] = $child->id;
            $childIds = $this->collectAllChildrenIds($child);
            $ids = array_merge($ids, $childIds);
        }

        return $ids;
    }

    /**
     * Delete all likes associated with comment(s)
     * 
     * @param Comment|int|array $input Comment object, ID, or array of IDs
     * @return int Number of deleted likes
     * 
     * @example | $this->deleteLikes($comment);
     */
    public function deleteLikes($input): int {
        $totalDeleted = 0;

        $commentIds = $this->convertToIdArray($input);

        if (empty($commentIds)) {
            return $totalDeleted;
        }

        /**
         * Double chunking strategy:
         * 
         * 1. Chunking even for already chunked inputs ensures this method
         *    remains performant when called directly with large arrays
         * 
         * 2. Inner chunkById prevents data loss during deletion by using progressive ID filtering
         *    rather than OFFSET/LIMIT, ensuring no records are skipped if data changes during processing
         */
        foreach (array_chunk($commentIds, 100) as $chunk) {
            UserLike::where('likeable_type', Comment::class)
                ->whereIn('likeable_id', $chunk)
                ->chunkById(100, function ($likes) use (&$totalDeleted) {
                    $deleted = DB::table('user_likes')
                        ->whereIn('id', $likes->pluck('id'))
                        ->delete();
                    $totalDeleted += $deleted;
                });
        }
        return $totalDeleted;
    }

    /**
     * Delete all reports associated with comment(s)
     * 
     * @param Comment|int|array $input Comment object, ID, or array of IDs
     * @return int Number of deleted reports
     * 
     * @example | $this->deleteReports($comment);
     */
    public function deleteReports($input): int {
        $totalDeleted = 0;

        $commentIds = $this->convertToIdArray($input);

        if (empty($commentIds)) {
            return $totalDeleted;
        }

        /**
         * Double chunking strategy:
         * 
         * 1. Chunking even for already chunked inputs ensures this method
         *    remains performant when called directly with large arrays
         * 
         * 2. Inner chunkById prevents data loss during deletion by using progressive ID filtering
         *    rather than OFFSET/LIMIT, ensuring no records are skipped if data changes during processing
         */
        foreach (array_chunk($commentIds, 100) as $chunk) {
            UserReport::where('reportable_type', Comment::class)
                ->whereIn('reportable_id', $chunk)
                ->chunkById(100, function ($reports) use (&$totalDeleted) {
                    $deleted = DB::table('user_reports')
                        ->whereIn('id', $reports->pluck('id'))
                        ->delete();
                    $totalDeleted += $deleted;
                });
        }

        return $totalDeleted;
    }


    /**
     * Helper method to normalize input to an array of IDs
     *
     * @param Comment|int|array $input
     * @return array Array of integer IDs or empty array if input is invalid
     * 
     * @example | $this->convertToIdArray($input);
     */
    private function convertToIdArray($input): array {
        if ($input instanceof Comment) {
            return [$input->id];
        }

        if (is_numeric($input)) {
            return [(int)$input];
        }

        if (is_array($input)) {
            return array_map('intval', $input);
        }

        return [];
    }
}
