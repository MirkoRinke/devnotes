<?php

namespace App\Services;

use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use App\Models\UserLike;
use App\Models\UserProfile;
use App\Models\UserReport;
use Illuminate\Support\Facades\DB;

class UserRelationService {

    /**
     * Create a user profile for a user
     * 
     * @param User $user
     * @return UserProfile
     */
    public function createUserProfile(User $user): UserProfile {
        return UserProfile::create([
            'user_id' => $user->id,
            'display_name' => $user->display_name ?? $user->name,
        ]);
    }

    /**
     * Check user name for moderation
     * 
     * @param User $user
     * @return void
     */
    public function checkUsername(User $user): void {
        app(UserModerationService::class)->checkAndReportUsername($user);
    }


    /**
     * Update user and profile display name, and perform moderation check
     * 
     * @param UserProfile $profile
     * @param string $newDisplayName
     * @return UserProfile
     */
    public function updateProfileDisplayName(UserProfile $profile) {
        // Check if the display name has changed
        if ($profile->wasChanged('display_name')) {
            // Update the user's display name
            $profile->user()->update([
                'display_name' => $profile->display_name
            ]);

            // Check name for partially forbidden words
            app(UserModerationService::class)->checkAndReportUsername($profile->user);
        }
    }


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
