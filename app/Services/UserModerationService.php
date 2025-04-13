<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserReport;
use App\Models\ForbiddenName;
use App\Models\UserProfile;

class UserModerationService {
    /**
     * Check both username and name for partially forbidden words
     * 
     * @param User $user The user to check
     * @return UserReport|null The created report or null if no report was created
     */
    public function checkAndReportUsername(User $user): ?UserReport {
        // Check if the user is an admin, system, or moderator
        // If so, skip the moderation check
        if ($user->role === 'admin' || $user->role === 'system' || $user->role === 'moderator') {
            return null;
        }

        // Check display_name
        $displayMatchedWord = $this->findForbiddenPartialMatch($user->display_name);
        if ($displayMatchedWord) {
            return $this->createAutoReport($user, $displayMatchedWord, "display_name '{$user->display_name}'");
        }

        // Check name
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
     */
    private function findForbiddenPartialMatch(string $name): ?string {
        $partialMatches = ForbiddenName::where('match_type', 'partial')->get(['name']);

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
     */
    private function createAutoReport(User $user, string $matchedWord, string $fieldInfo): UserReport {
        // Check if a report already exists for this user
        $existingReport = UserReport::where(['user_id' => 2, 'reportable_id' => $user->id, 'reportable_type' => User::class])->first();

        if ($existingReport) {
            // Update the existing report instead of creating a new one
            $existingReport->update([
                'reason' => "Automatic moderation: User {$fieldInfo} contains potentially inappropriate word '{$matchedWord}'. (Updated)"
            ]);
            $report = $existingReport;
        } else {
            // Create a new report if none exists
            $report = UserReport::create([
                'user_id' => 2, // System user ID
                'reportable_id' => $user->id,
                'reportable_type' => User::class,
                'type' => 'user',
                'reason' => "Automatic moderation: User {$fieldInfo} contains potentially inappropriate word '{$matchedWord}'."
            ]);

            // Only increment reports_count for new reports
            $profile = UserProfile::where('user_id', $user->id)->first();
            if ($profile) {
                $profile->increment('reports_count');
            }
        }

        return $report;
    }
}
