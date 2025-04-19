<?php

namespace App\Services;

use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use App\Models\UserLike;
use App\Models\UserReport;
use Illuminate\Support\Facades\DB;

class UserRelationService {

    /**
     * Transfer all posts from a user to the system user
     * 
     * @param User $user
     * @param int $systemUserId Default: 3
     * @return int Number of transferred posts
     */
    public function transferPosts(User $user, int $systemUserId = 3): int {
        $totalTransferred = 0;

        Post::where('user_id', $user->id)
            ->chunkById(100, function ($posts) use ($systemUserId, &$totalTransferred) {
                $ids = $posts->pluck('id')->toArray();
                $updated = DB::table('posts')
                    ->whereIn('id', $ids)
                    ->update(['user_id' => $systemUserId]);
                $totalTransferred += $updated;
            });

        return $totalTransferred;
    }

    /**
     * Transfer all comments from a user to the system user
     * 
     * @param User $user
     * @param int $systemUserId Default: 3
     * @return int Number of transferred comments
     */
    public function transferComments(User $user, int $systemUserId = 3): int {
        $totalTransferred = 0;

        Comment::where('user_id', $user->id)
            ->chunkById(100, function ($comments) use ($systemUserId, &$totalTransferred) {
                $ids = $comments->pluck('id')->toArray();
                $updated = DB::table('comments')
                    ->whereIn('id', $ids)
                    ->update(['user_id' => $systemUserId]);
                $totalTransferred += $updated;
            });

        return $totalTransferred;
    }

    /**
     * Delete all reports associated with a user
     * 
     * @param User $user
     * @return int Number of deleted reports
     */
    public function deleteReports(User $user): int {
        $totalDeleted = 0;

        UserReport::where('reportable_type', User::class)
            ->where('reportable_id', $user->id)
            ->chunkById(100, function ($reports) use (&$totalDeleted) {
                $ids = $reports->pluck('id')->toArray();
                $deleted = DB::table('user_reports')
                    ->whereIn('id', $ids)
                    ->delete();
                $totalDeleted += $deleted;
            });

        return $totalDeleted;
    }

    /**
     * Delete all likes associated with a user
     * 
     * @param User $user
     * @return int Number of deleted likes
     */
    public function deleteLikes(User $user): int {
        $totalDeleted = 0;

        UserLike::where('likeable_type', User::class)
            ->where('likeable_id', $user->id)
            ->chunkById(100, function ($likes) use (&$totalDeleted) {
                $ids = $likes->pluck('id')->toArray();
                $deleted = DB::table('user_likes')
                    ->whereIn('id', $ids)
                    ->delete();
                $totalDeleted += $deleted;
            });

        return $totalDeleted;
    }
}
