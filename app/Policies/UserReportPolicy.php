<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserReport;
use App\Models\Post;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserReportPolicy {
    use HandlesAuthorization;

    /**
     * The user can only delete their own report.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\UserReport  $userReport
     * @return mixed
     */
    public function delete(User $user, UserReport $userReport) {
        return $user->id === $userReport->user_id;
    }

    /**
     * Determine if the user can create a report.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Post  $post
     * @return mixed
     */
    public function create(User $user, Post $post) {
        // Users shouldn't report their own posts
        return $user->id !== $post->user_id;
    }
}
