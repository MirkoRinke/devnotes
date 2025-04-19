<?php

namespace App\Services;

use App\Models\Post;
use App\Models\UserLike;
use App\Models\UserReport;
use Illuminate\Support\Facades\DB;

use App\Services\CommentRelationService;

class PostRelationService {

    /**
     * The services used in the controller
     */
    protected $commentRelationService;

    /**
     * Constructor to initialize the services
     */
    public function __construct(CommentRelationService $commentRelationService) {
        $this->commentRelationService = $commentRelationService;
    }


    /**
     * Delete all comments associated with a post
     * 
     * @param Post $post
     * @return int Number of deleted comments
     */
    public function deleteComments(Post $post): int {
        $totalDeleted = 0;
        $commentIds = [];

        // Get all comments associated with the post and delete their likes and reports
        $post->comments()->chunkById(100, function ($comments) use (&$commentIds) {
            foreach ($comments as $comment) {
                $commentIds[] = $comment->id;
                $this->commentRelationService->deleteReports($comment);
                $this->commentRelationService->deleteLikes($comment);
            }
        });

        // Delete all comments in chunks to avoid memory issues and to ensure that the database can handle the load
        if (!empty($commentIds)) {
            foreach (array_chunk($commentIds, 100) as $chunk) {
                $deleted = DB::table('comments')->whereIn('id', $chunk)->delete();
                $totalDeleted += $deleted;
            }
        }
        return $totalDeleted;
    }


    /**
     * Delete all reports associated with a post
     * 
     * @param Post $post
     * @return int Number of deleted reports
     */
    public function deleteReports(Post $post): int {
        $totalDeleted = 0;

        UserReport::where('reportable_type', Post::class)
            ->where('reportable_id', $post->id)
            ->chunkById(100, function ($reports) use (&$totalDeleted) {
                $deleted = DB::table('user_reports')
                    ->whereIn('id', $reports->pluck('id'))
                    ->delete();
                $totalDeleted += $deleted;
            });

        return $totalDeleted;
    }

    /**
     * Delete all likes associated with a post
     * 
     * @param Post $post
     * @return int Number of deleted likes
     */
    public function deleteLikes(Post $post): int {
        $totalDeleted = 0;

        UserLike::where('likeable_type', Post::class)
            ->where('likeable_id', $post->id)
            ->chunkById(100, function ($likes) use (&$totalDeleted) {
                $deleted = DB::table('user_likes')
                    ->whereIn('id', $likes->pluck('id'))
                    ->delete();
                $totalDeleted += $deleted;
            });

        return $totalDeleted;
    }
}
