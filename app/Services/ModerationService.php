<?php

namespace App\Services;

use Illuminate\Http\Request;

use App\Models\Post;
use App\Models\Comment;

/**
 * Class ModerationService
 * 
 * This class handles moderation updates for content such as posts and comments.
 * It tracks changes made to the content and creates moderation log entries.
 * 
 */
class ModerationService {

    /**
     * Handle moderation updates for content
     * 
     * @param mixed $model The model being moderated (Post, Comment, etc.)
     * @param array $validatedData The validated data
     * @param Request $request The current request
     * @return mixed The updated model
     * 
     * @example |  $post = $this->moderationService->handleModerationUpdate(
     *                  $post,
     *                  array_merge(
     *                      $validatedData,
     *                      [
     *                          'is_updated' => true,
     *                          'updated_by_role' => $request->user()->role
     *                      ]
     *                  ),
     *                  $request,
     *                  ['title', 'code', 'description', 'images', 'resources', 'language', 'category', 'post_type', 'technology', 'tags', 'status'],
     *                  'post'
     *              );
     */
    public function handleModerationUpdate($model, array $validatedData, Request $request, array $trackFields, $moderationEntry): mixed {
        // Save original data for comparison
        $originalData = $this->getOriginalData($model, $trackFields);

        $newEntry = $this->createModerationEntry($request, $moderationEntry);

        // Apply changes to model
        foreach ($validatedData as $key => $value) {
            if ($key !== 'moderation_reason') {
                $model->$key = $value;
            }
        }

        // Create moderation log entry
        $model = $this->createModerationLogEntry($model, $originalData, $newEntry);

        return $model;
    }

    /**
     * Get the original data of the model for tracking changes
     * 
     * @param mixed $model The model being moderated
     * @param array $fields The fields to track
     * @return array The original data
     * 
     * @example | $originalData = $this->getOriginalData($model, $trackFields);
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
     * 
     * @example | $newEntry = $this->createModerationEntry($request, $moderationEntry);
     */
    private function createModerationEntry(Request $request, $moderationEntry): array {
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
     * @return mixed The updated model with the moderation log entry
     * 
     * @example | $this->createModerationLogEntry($model, $originalData, $newEntry);
     */
    private function createModerationLogEntry($model, array $originalData, array $newEntry): mixed {
        if (($model instanceof Post || $model instanceof Comment)) {
            $newEntry = $this->getModelChanges($model, $originalData, $newEntry);
        }

        $moderationLog = $model->moderation_info ?? [];

        if (!empty($moderationLog) && !isset($moderationLog[0])) {
            $moderationLog = [$moderationLog];
        }

        array_unshift($moderationLog, $newEntry);

        $model->moderation_info = $moderationLog;

        return $model;
    }

    /**
     * Get the changes made to the model
     * 
     * @param mixed $model The model being moderated
     * @param array $originalData The original data before changes
     * @return array|null The changes made to the model or null
     * 
     * @example | $newEntry = $this->getModelChanges($model, $originalData, $newEntry);
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
     * 
     * @param string $string The string to check
     * @return bool True if the string is valid JSON, false otherwise
     * 
     * @example | $this->isValidJson($string);
     */
    private function isValidJson($string): bool {
        if (!is_string($string)) {
            return false;
        }

        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
