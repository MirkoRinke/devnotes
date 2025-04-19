<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

use App\Models\Comment;
use App\Models\Post;
use App\Models\UserLike;
use App\Models\UserReport;

class CommentRelationService {
    /**
     * Update the last_comment_at timestamp of the parent post
     *
     * @param Comment $comment
     * @return void
     */
    public function updateLastCommentAt(Comment $comment): void {
        Post::where('id', $comment->post_id)
            ->update(['last_comment_at' => now()]);
    }

    /**
     * Update the comments_count in the parent post
     *
     * @param Comment $comment
     * @param string $operation Operation to perform ('increment' or 'decrement')
     * @return void
     */
    public function updateCommentsCount(Comment $comment, string $operation = 'increment'): void {
        Post::where('id', $comment->post_id)
            ->$operation('comments_count', 1);
    }

    /**
     * Delete all child comments associated with the comment
     *
     * @param Comment $comment The parent comment
     * @return int Number of deleted comments
     */
    public function deleteChildren(Comment $comment): int {
        $childrenIds = [];
        $this->collectAllChildrenIds($comment, $childrenIds);

        if (empty($childrenIds)) {
            return 0;
        }

        $totalDeleted = 0;
        foreach (array_chunk($childrenIds, 100) as $chunk) {
            $deleted = Comment::whereIn('id', $chunk)->delete();
            $totalDeleted += $deleted;
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
     * @param array $ids Array to store collected IDs
     */
    private function collectAllChildrenIds(Comment $comment, array &$ids): void {

        $children = $comment->children()->get(['id']);

        foreach ($children as $child) {
            $ids[] = $child->id;
            $this->collectAllChildrenIds($child, $ids);
        }
    }

    /**
     * Delete all likes associated with a comment
     * 
     * @param Comment $comment
     * @return int Number of deleted likes
     */
    public function deleteLikes(Comment $comment): int {
        $totalDeleted = 0;
        UserLike::where('likeable_type', Comment::class)
            ->where('likeable_id', $comment->id)
            ->chunkById(100, function ($likes) use (&$totalDeleted) {
                $deleted = DB::table('user_likes')
                    ->whereIn('id', $likes->pluck('id'))
                    ->delete();
                $totalDeleted += $deleted;
            });

        return $totalDeleted;
    }

    /**
     * Delete all reports associated with a comment
     * 
     * @param Comment $comment
     * @return int Number of deleted reports
     */
    public function deleteReports(Comment $comment): int {
        $totalDeleted = 0;
        UserReport::where('reportable_type', Comment::class)
            ->where('reportable_id', $comment->id)
            ->chunkById(100, function ($reports) use (&$totalDeleted) {
                $deleted = DB::table('user_reports')
                    ->whereIn('id', $reports->pluck('id'))
                    ->delete();
                $totalDeleted += $deleted;
            });

        return $totalDeleted;
    }
}
