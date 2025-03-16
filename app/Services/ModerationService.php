<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserReport;
use App\Models\ForbiddenName;
use App\Models\UserProfile;

class ModerationService {
    /**
     * Check username for partially forbidden words and create a report if needed
     * 
     * @param User $user The user to check
     * @return UserReport|null The created report or null if no report was created
     */
    public function checkAndReportUsername(User $user): ?UserReport {
        $displayName = $user->display_name;
        $matchedWord = $this->findForbiddenPartialMatch($displayName);

        if ($matchedWord) {
            return $this->createAutoReport($user, $matchedWord);
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
     * @return UserReport The created report
     */
    private function createAutoReport(User $user, string $matchedWord): UserReport {
        // Check if a report already exists for this user
        $existingReport = UserReport::where(['user_id' => 1, 'reportable_type' => User::class])->first();

        if ($existingReport) {
            // Update the existing report instead of creating a new one
            $existingReport->update([
                'reason' => "Automatic moderation: Display name '{$user->display_name}' contains potentially inappropriate word '{$matchedWord}'. (Updated)"
            ]);
            $report = $existingReport;
        } else {
            // Create a new report if none exists
            $report = UserReport::create([
                'user_id' => 1, // System user ID
                'reportable_id' => $user->id,
                'reportable_type' => User::class,
                'type' => 'user',
                'reason' => "Automatic moderation: Display name '{$user->display_name}' contains potentially inappropriate word '{$matchedWord}'."
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
