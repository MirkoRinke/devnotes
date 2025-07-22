<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;

use App\Traits\ApiResponses;

use App\Services\UserRelationService;

use Exception;

/**
 * This GuestAccountService class is responsible for managing guest accounts.
 * It includes methods for creating a guest account, resetting a guest account,
 */
class GuestAccountService {

    /**
     *  The traits used in the controller
     */
    use ApiResponses;

    /**
     *  The Service used in the controller
     */
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
     * 
     * @example | $this->createGuestAccount();
     */
    public function createGuestAccount() {
        $user = new User();

        $user->name = 'Guest';
        $user->display_name = 'Guest';
        $user->email = 'guest@system.local';
        $user->password = Hash::make('sicheresPasswort123'); //!Todo Use a secure password
        $user->role = 'user';
        $user->email_verified_at = now();
        $user->account_purpose = 'guest';

        $user->save();

        $this->userRelationService->createUserProfile($user);

        return $user;
    }

    /**
     * Special handling for guest account deletion and recreation
     * 
     * This method deletes all posts and comments associated with the guest account,
     * deletes all reports and likes associated with the user, and then recreates the guest account.
     * 
     * @param User $user The guest user to be reset
     * @return bool True if the operation was successful, false otherwise
     * 
     * @example | $success = $this->guestAccountService->resetGuestAccount($user);
     * 
     */
    public function resetGuestAccount(User $user): bool {
        try {
            DB::transaction(function () use ($user) {
                $this->deletePosts($user);
                $this->deleteComments($user);

                $this->userRelationService->deleteReports($user);
                $this->userRelationService->deleteLikes($user);

                $user->delete();
            });

            $this->createGuestAccount();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }



    /**
     * Delete all posts from a user
     * 
     * @param User $user
     * @return int Number of deleted posts
     * 
     * @example | $this->deletePosts($user);
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
     * 
     * @example | $this->deleteComments($user);
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
