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
     * @param array $trackFields The fields to track changes for
     * @param string $moderationEntry The type of moderation entry
     * @param array $trackRelations Optional array of relations to track ['relationName' => 'fieldToUse'] 
     * @param array $relationChanges Optional array containing new values for relations ( e.g., $validatedData['relations'] ) 
     * @return mixed The updated model
     * 
     * @example |  $post = $this->moderationService->handleModerationUpdate(
     *                  $post,
     *                  $validatedData,
     *                  $request,
     *                  ['title', 'code', 'description', 'images', 'resources', 'language', 'category', 'post_type', 'technology', 'status'],
     *                  'post',
     *                  ['tags' => 'name'],
     *                  $relationChanges
     *              );
     */
    public function handleModerationUpdate($model, array $validatedData, Request $request, array $trackFields, $moderationEntry, array $trackRelations = [], array $relationChanges = []): mixed {
        // Save original data for comparison
        $originalData = $this->getOriginalData($model, $trackFields, $trackRelations);

        $newEntry = $this->createModerationEntry($request, $moderationEntry);

        // Apply changes to model
        foreach ($validatedData as $key => $value) {
            if ($key !== 'moderation_reason' && !array_key_exists($key, $trackRelations)) {
                $model->$key = $value;
            }
        }

        // Create moderation log entry
        $model = $this->createModerationLogEntry($model, $originalData, $newEntry, $trackRelations, $relationChanges);

        return $model;
    }

    /**
     * Get the original data of the model for tracking changes
     * 
     * @param mixed $model The model being moderated
     * @param array $fields The fields to track from the model
     * @param array $relations Optional array of relations to track ['relationName' => 'fieldToUse']
     * @return array The original data including both direct fields and relation values
     * 
     * @example | $originalData = $this->getOriginalData($model, $trackFields, ['tags' => 'name']);
     */
    private function getOriginalData($model, array $fields, array $relations = []): array {
        $originalData = [];

        foreach ($fields as $field) {
            if (isset($model->$field)) {
                $originalData[$field] = $model->$field;
            }
        }

        foreach ($relations as $relation => $field) {
            if (method_exists($model, $relation)) {
                $originalData[$relation] = $model->$relation()->pluck($field)->toArray();
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
     * @param array $newEntry The moderation entry to update with changes
     * @param array $relations Optional array of relations to track ['relationName' => 'fieldToUse']
     * @param array $relationChanges Optional array containing new values for tracked relations
     * @return mixed The updated model with the moderation log entry
     * 
     * @example | $model = $this->createModerationLogEntry($model, $originalData, $newEntry, ['tags' => 'name'], $relationChanges);
     */
    private function createModerationLogEntry($model, array $originalData, array $newEntry, array $relations = [], array $relationChanges = []): mixed {
        if (($model instanceof Post || $model instanceof Comment)) {
            $newEntry = $this->getModelChanges($model, $originalData, $newEntry);
            $newEntry = $this->trackRelationChanges($newEntry, $originalData, $relations, $relationChanges);
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
     * Track changes in model relations
     * 
     * @param array $newEntry The moderation entry to update with relation changes
     * @param array $originalData The original relation data
     * @param array $relations Array of relations to track ['relationName' => 'fieldToUse']
     * @param array $relationChanges Array containing new values for relations
     * @return array The updated moderation entry with relation changes
     * 
     * @example | $newEntry = $this->trackRelationChanges($newEntry, $originalData, ['tags' => 'name'], ['tags' => ['php', 'laravel']]);
     */
    private function trackRelationChanges(array $newEntry, array $originalData, array $relations, array $relationChanges): array {
        if (!empty($relations) && !empty($relationChanges)) {
            $changes = $newEntry['changes'] ?? [];

            /**
             * Note: Using "$relation => $field" syntax is required even though $field isn't used.
             * With associative arrays, "as $relation" would extract values, not keys.
             */
            foreach ($relations as $relation => $field) {
                if (isset($relationChanges[$relation]) && isset($originalData[$relation])) {

                    $newValues = $relationChanges[$relation];
                    $oldValues = $originalData[$relation];

                    sort($newValues);
                    sort($oldValues);

                    if (array_diff($oldValues, $newValues) || array_diff($newValues, $oldValues)) {
                        $changes[$relation] = [
                            'from' => $originalData[$relation],
                            'to' => $relationChanges[$relation]
                        ];
                    }
                }
            }
            $newEntry['changes'] = !empty($changes) ? $changes : null;
        }
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
