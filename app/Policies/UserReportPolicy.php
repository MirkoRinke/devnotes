<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserReport;

use App\Traits\PolicyChecks;

/**
 * UserReportPolicy
 * 
 * This policy class is used to authorize actions on the UserReport model.
 * It checks if the user is an admin or the owner of the report.
 */
class UserReportPolicy {

    /**
     * The traits used in the policy
     */
    use PolicyChecks;

    /**
     * Determine if the user can view all reports.
     * 
     * @param User $user
     * @return bool
     * 
     * @example | $this->authorize('viewAny', UserReport::class);
     */
    public function viewAny(User $user): bool {
        return $this->isAdmin($user);
    }

    /**
     * The user can only delete their own report.
     * 
     * @param User $user
     * @param UserReport $userReport
     * @return bool
     * 
     * @example | $this->authorize('delete', $userReport);
     */
    public function delete(User $user, UserReport $userReport): bool {
        if ($this->isAdmin($user)) {
            return true;
        }
        return $this->isOwner($user, $userReport);
    }
}
