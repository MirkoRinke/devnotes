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
     * Determine if the user can view all reports (admin function).
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function viewAny(User $user) {
        // Only admins can view all reports
        return $user->role === 'admin';
    }
}
