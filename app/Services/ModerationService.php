<?php

namespace App\Services;

use Illuminate\Http\Request;

use App\Models\Post;
use App\Models\Comment;

class ModerationService {

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
            if (!in_array($field, ['updated_by', 'is_edited', 'updated_by_role', 'moderation_info', 'external_source_previews'])) {

                if (is_string($newValue) && $this->isValidJson($newValue)) {
                    $newValue = json_decode($newValue, true);
                }

                $changes[$field] = [
                    'from' => $originalData[$field] ?? null,
                    'to' => $newValue
                ];
            }
        }

        $newEntry = array_merge($newEntry, [
            'changes' => !empty($changes) ? $changes : null
        ]);
        return $newEntry;
    }

    /**
     * Check if a string is valid JSON
     */
    private function isValidJson($string): bool {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
