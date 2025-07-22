<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserReport;
use App\Models\ForbiddenName;
use App\Models\UserProfile;

use App\Traits\CacheHelper;

/**
 * UserModerationService is responsible for checking and reporting users
 * based on their usernames and display names.
 * 
 * It checks for forbidden words in the user's name and creates reports
 * if any matches are found.
 */
class UserModerationService {

    /**
     *  The traits used in the Service
     */
    use CacheHelper;

    /**
     * The services used in the Service
     */
    protected $snapshotService;

    /**
     * Constructor to initialize the services
     */
    public function __construct(SnapshotService $snapshotService) {
        $this->snapshotService = $snapshotService;
    }

    /**
     * Check both username and name for partially forbidden words
     * 
     *! Example: admin1234 = AutoReport / 1234admin456 = AutoReport
     *! ( Exact match is checked by NotForbiddenName in the registration process before the user is created )
     * 
     * @param User $user The user to check
     * @return UserReport|null The created report or null if no report was created
     * 
     * @example | app(UserModerationService::class)->checkAndReportUsername($user);
     */
    public function checkAndReportUsername(User $user): ?UserReport {
        /**
         * Check if the user is an admin, system, or moderator
         * If so, skip the moderation check
         */
        if ($user->role === 'admin' || $user->role === 'system' || $user->role === 'moderator') {
            return null;
        }

        $displayMatchedWord = $this->findForbiddenPartialMatch($user->display_name);
        if ($displayMatchedWord) {
            return $this->createAutoReport($user, $displayMatchedWord, "display_name '{$user->display_name}'");
        }

        $nameMatchedWord = $this->findForbiddenPartialMatch($user->name);
        if ($nameMatchedWord) {
            return $this->createAutoReport($user, $nameMatchedWord, "name '{$user->name}'");
        }

        return null;
    }

    /**
     * Find partially forbidden words in the given name
     * 
     * @param string $name The name to check
     * @return string|null The found forbidden word or null
     * 
     * @example | $this->findForbiddenPartialMatch($user->name);
     */
    private function findForbiddenPartialMatch(string $name): ?string {

        $cacheKey = $this->generateSimpleCacheKey('forbidden_names_partial');

        $partialMatches = $this->cacheData($cacheKey, 3600, function () {
            return ForbiddenName::where('match_type', 'partial')->get();
        });

        foreach ($partialMatches as $forbidden) {
            if (stripos($name, $forbidden->name) !== false) {
                return $forbidden->name;
            }
        }

        return null;
    }

    /**
     * Create an automatic report for the user
     * 
     * @param User $user The user to report
     * @param string $matchedWord The found forbidden word
     * @param string $fieldInfo Information about which field contained the word
     * @return UserReport The created report
     * 
     * @example | $this->createAutoReport($user, $nameMatchedWord, "name '{$user->name}'");
     */
    private function createAutoReport(User $user, string $matchedWord, string $fieldInfo): UserReport {
        /**
         * Check if a report already exists for this user ( user_id = 2  is the system user )
         */
        $existingReport = UserReport::where(['user_id' => 2, 'reportable_id' => $user->id, 'reportable_type' => UserProfile::class])->first();

        $reportableSnapshot = $this->snapshotService->createSnapshot($user->profile, UserProfile::class);

        if ($existingReport) {
            $existingReport->update([
                'reason' => "Automatic moderation: User {$fieldInfo} contains potentially inappropriate word '{$matchedWord}'. (Updated)",
                'reportable_snapshot' => $reportableSnapshot,
            ]);
            $report = $existingReport;
        } else {
            $report = UserReport::create([
                'user_id' => 2, // System user ID
                'reportable_id' => $user->id,
                'reportable_type' => UserProfile::class,
                'type' => 'userProfile',
                'reason' => "Automatic moderation: User {$fieldInfo} contains potentially inappropriate word '{$matchedWord}'.",
                'reportable_snapshot' => $reportableSnapshot
            ]);

            $profile = UserProfile::where('user_id', $user->id)->first();
            if ($profile) {
                $profile->increment('reports_count');
            }
        }

        return $report;
    }
}
