<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use App\Services\UserRelationService;

class GuestAccountService {

    protected $userRelationService;

    /**
     * Constructor to initialize the services
     */
    public function __construct(UserRelationService $userRelationService) {
        $this->userRelationService = $userRelationService;
    }

    /**
     * Create a guest account for a user and the related profile
     * 
     * @param User $user
     * @return User
     */
    public function createGuestAccount() {
        $user = User::create([
            'name' => 'Guest',
            'display_name' => 'Guest',
            'email' => 'guest@system.local',
            'password' => Hash::make('sicheresPasswort123'),
            'role' => 'user',
            'email_verified_at' => now(),
            'account_purpose' => 'guest',
        ]);

        $this->userRelationService->createUserProfile($user);

        return $user;
    }

    /**
     * Delete all posts from a user
     * 
     * @param User $user
     * @return int Number of deleted posts
     */
    public function deletePosts(User $user): int {
        $totalDeleted = 0;

        Post::where('user_id', $user->id)
            ->chunkById(100, function ($posts) use (&$totalDeleted) {
                $ids = $posts->pluck('id')->toArray();
                $deleted = DB::table('posts')
                    ->whereIn('id', $ids)
                    ->delete();
                $totalDeleted += $deleted;
            });

        return $totalDeleted;
    }

    /**
     * Delete all comments from a user
     * 
     * @param User $user
     * @return int Number of deleted comments
     */
    public function deleteComments(User $user): int {
        $totalDeleted = 0;

        Comment::where('user_id', $user->id)
            ->chunkById(100, function ($comments) use (&$totalDeleted) {
                $ids = $comments->pluck('id')->toArray();
                $deleted = DB::table('comments')
                    ->whereIn('id', $ids)
                    ->delete();
                $totalDeleted += $deleted;
            });

        return $totalDeleted;
    }
}
