<?php

namespace App\Services;

use Illuminate\Http\Request;

use App\Models\User;
use App\Models\UserReport;
use App\Models\ForbiddenName;
use App\Models\Post;
use App\Models\Comment;
use App\Models\UserProfile;

class ModerationService {
    /**
     * Check both username and name for partially forbidden words
     * 
     * @param User $user The user to check
     * @return UserReport|null The created report or null if no report was created
     */
    public function checkAndReportUsername(User $user): ?UserReport {
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
        $existingReport = UserReport::where(['user_id' => 1, 'reportable_id' => $user->id, 'reportable_type' => User::class])->first();

        if ($existingReport) {
            // Update the existing report instead of creating a new one
            $existingReport->update([
                'reason' => "Automatic moderation: User {$fieldInfo} contains potentially inappropriate word '{$matchedWord}'. (Updated)"
            ]);
            $report = $existingReport;
        } else {
            // Create a new report if none exists
            $report = UserReport::create([
                'user_id' => 1, // System user ID
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


    /**
     * Handle moderation updates for content
     * 
     * @param mixed $model The model being moderated (Post, Comment, etc.)
     * @param array $validatedData The validated data
     * @param Request $request The current request
     * @return mixed The updated model
     */
    public function handleModerationUpdate($model, array $validatedData, Request $request, array $trackFields, $moderationEntry): mixed {
        // Save original data for comparison
        $originalData = $this->getOriginalData($model, $trackFields);

        $newEntry = $this->createModerationEntry($request, $moderationEntry, $validatedData);

        // Apply changes to model
        foreach ($validatedData as $key => $value) {
            if ($key !== 'moderation_reason') {
                $model->$key = $value;
            }
        }

        // Create moderation log entry
        $this->createModerationLogEntry($model, $originalData, $newEntry, $request);

        return $model;
    }

    /**
     * Get the original data of the model for tracking changes
     * 
     * @param mixed $model The model being moderated
     * @param array $fields The fields to track
     * @return array The original data
     */
    private function getOriginalData($model, array $fields): array {
        $originalData = [];

        foreach ($fields as $field) {
            if (isset($model->$field)) {
                $originalData[$field] = $model->$field;
            }
        }

        return $originalData;
    }

    /**
     * Create a moderation entry for the model
     * 
     * @param mixed $model The model being moderated
     * @param Request $request The current request
     * @return array The created moderation entry
     */
    private function createModerationEntry(Request $request, $moderationEntry, $validatedData): array {
        $baseEntry = [
            'user_id' => $request->user()->id,
            'username' => $request->user()->name,
            'role' => $request->user()->role,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($moderationEntry === 'post' || $moderationEntry === 'comment') {
            return array_merge($baseEntry, [
                'reason' => $request->moderation_reason,
                'action' => 'updated',
            ]);
        } else if ($moderationEntry === 'banUser') {
            return array_merge($baseEntry, [
                'reason' => $request->moderation_reason,
                'action' => 'ban'
            ]);
        } else if ($moderationEntry === 'unbanUser') {
            return array_merge($baseEntry, [
                'reason' => $request->moderation_reason,
                'action' => 'unban'
            ]);
        }

        return [];
    }


    /**
     * Create a moderation log entry for the model
     * 
     * @param mixed $model The model being moderated
     * @param array $originalData The original data before changes
     * @param array $validatedData The validated data
     * @param Request $request The current request
     */
    private function createModerationLogEntry($model, array $originalData, array $newEntry, Request $request): void {
        if (($model instanceof Post || $model instanceof Comment)) {
            $newEntry = $this->getModelChanges($model, $originalData, $newEntry);
        }

        $moderationLog = $model->moderation_info ?? [];

        if (!empty($moderationLog) && !isset($moderationLog[0])) {
            $moderationLog = [$moderationLog];
        }

        array_unshift($moderationLog, $newEntry);
        $model->moderation_info = $moderationLog;
    }

    /**
     * Get the changes made to the model
     * 
     * @param mixed $model The model being moderated
     * @param array $originalData The original data before changes
     * @return array|null The changes made to the model or null
     */
    private function getModelChanges($model, array $originalData, array  $newEntry): array {
        $dirtyFields = $model->getDirty();

        $changes = [];
        foreach ($dirtyFields as $field => $newValue) {
            if (!in_array($field, ['updated_by', 'is_edited', 'updated_by_role', 'moderation_info'])) {
                $changes[$field] = [
                    'from' => $originalData[$field] ?? null,
                    'to' => $newValue
                ];
            }
        }

        $newEntry = array_merge($newEntry, [
            'changes' => $changes
        ]);
        return $newEntry;
    }
}
