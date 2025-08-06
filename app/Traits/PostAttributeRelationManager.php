<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use App\Models\Post;
use App\Models\PostAllowedValue;

/**
 * Trait for managing post attribute relationships (tags, languages, technologies)
 * Provides optimized batch operations for creating and synchronizing attribute relations.
 */
trait PostAttributeRelationManager {

    /**
     * Synchronize multiple relations for a post in one optimized operation
     * 
     * @param Post $post The post to synchronize relations for
     * @param mixed $user The user performing the action
     * @param array $relationData Associative array of relation types and values
     *              Example: ['tag' => ['php', 'laravel'], 'language' => ['php', 'javascript']]
     * @return void
     * 
     * @example | $this->syncMultipleRelations($post, $user, [
     *              'tag' => $tagNames,
     *              'language' => $languageNames
     *              'technology' => $technologyNames
     *          ]);
     */
    protected function syncMultipleRelations(Post $post, $user, array $relationData): void {
        /**
         * Mapping of relation types to model relation methods
         */
        $relationMap = [
            'tag' => 'tags',
            'language' => 'languages',
            'technology' => 'technologies',
        ];

        [$relationData, $exportCurrentIds] = $this->syncEmptyAndUnchanged($relationData, $post, $relationMap);
        if (empty($relationData)) {
            return;
        }


        [$normalizedValues, $originalValueMap] = $this->normalizeRelationValues($relationData);

        $existingRelations = $this->existingRelations($normalizedValues);

        $relationIds = $this->processRelationValues($user, $normalizedValues, $originalValueMap, $existingRelations);

        /**
         * Synchronize the relations in the pivot tables
         */
        foreach ($relationIds as $type => $ids) {
            if (isset($relationMap[$type])) {
                $currentIds = $exportCurrentIds[$type];

                sort($currentIds);
                sort($ids);

                $toRemove = array_diff($currentIds, $ids);
                $toAdd = array_diff($ids, $currentIds);

                $pivotTable = $post->{$relationMap[$type]}()->getTable();

                if (!empty($toRemove)) {
                    DB::table($pivotTable)->where('post_id', $post->id)->whereIn('post_allowed_value_id', $toRemove)->delete();
                }

                if (!empty($toAdd)) {
                    $pivotData = [];
                    foreach ($toAdd as $id) {
                        $pivotData[] = [
                            'post_id' => $post->id,
                            'post_allowed_value_id' => $id,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }
                    DB::table($pivotTable)->insert($pivotData);
                }
            }
        }
    }


    /**
     * Removes empty or unchanged relations from the provided relation data
     * and syncs (clears) relations in the database if an empty array is provided.
     *
     * - Relations missing in the request are ignored.
     * - Relations with an empty array are cleared in the database (sync([])).
     * - Relations whose values have not changed are skipped.
     * - Only changed relations are returned for further processing.
     *
     * @param array $relationData The relation data to check.
     * @param Post $post The post instance whose relations are checked
     * @param array $relationMap Mapping of relation types to model relation methods
     * @return array Filtered relations that actually need to be synchronized
     *
     * @example | $relationData = $this->syncEmptyAndUnchanged($relationData, $post, $relationMap);
     */
    protected function syncEmptyAndUnchanged($relationData, Post $post, $relationMap): array {
        $exportCurrentIds = [];

        foreach ($relationData as $type => $values) {
            // Skip if the values are not set
            if ($values === null) {
                unset($relationData[$type]);
                continue;
            }

            $currentIds = $post->{$relationMap[$type]}()->pluck('post_allowed_value_id', 'name')->toArray();
            $exportCurrentIds[$type] = $currentIds;


            $names = array_keys($currentIds);

            $names = array_map('strtolower', $names);

            $compareValues = array_map('strtolower', $values);
            sort($compareValues);
            sort($names);

            if ($compareValues === $names) {
                unset($relationData[$type]);
                continue;
            }

            if (empty($values)) {
                if (isset($relationMap[$type])) {
                    $post->{$relationMap[$type]}()->sync([]);
                }
                unset($relationData[$type]);
            }
        }

        return [$relationData, $exportCurrentIds];
    }


    /**
     * Normalize relation values by trimming and converting to lowercase
     * 
     * @param array $relationData The relation data to normalize
     * @return array Normalized relation values and a map of original values
     * 
     * @example | [$normalizedValues, $originalValueMap] = $this->normalizeRelationValues($relationData);
     */
    protected function normalizeRelationValues($relationData) {
        $normalizedValues = [];
        $originalValueMap = [];

        foreach ($relationData as $type => $values) {
            $normalizedValues[$type] = [];

            foreach ($values as $value) {
                $normalized = strtolower(trim($value));
                $normalizedValues[$type][] = $normalized;

                $originalValueMap[$type][$normalized] = $value;
            }
        }

        return [$normalizedValues, $originalValueMap];
    }


    /**
     * Retrieve existing relations from the database for the given normalized values
     * 
     * @param array $normalizedValues The normalized relation values
     * @return array Associative array of existing relations indexed by type and normalized value
     * 
     * @example | $existingRelations = $this->existingRelations($normalizedValues);
     */
    protected function existingRelations(array $normalizedValues): array {
        $existingRelations = [];
        $allQueries = [];

        foreach ($normalizedValues as $type => $typeValues) {
            $allQueries[$type] = PostAllowedValue::whereIn(DB::raw('LOWER(TRIM(name))'), $typeValues)
                ->where('type', $type);

            if (!isset($existingRelations[$type])) {
                $existingRelations[$type] = [];
            }
        }

        foreach ($allQueries as $type => $query) {
            $results = $query->get();

            foreach ($results as $relation) {
                $existingRelations[$type][strtolower(trim($relation->name))] = $relation;
            }
        }
        return $existingRelations;
    }


    /**
     * Process normalized relation values, creating new relations as needed
     * 
     * Note: Types like 'category', 'post_type', and 'status' should not be created via user input.
     * 
     * @param mixed $user The user performing the action
     * @param array $normalizedValues The normalized relation values
     * @param array $originalValueMap Map of original values for reference
     * @param array $existingRelations Existing relations indexed by type and normalized value
     * @return array Associative array of relation IDs indexed by type
     * 
     * @example | $relationIds = $this->processRelationValues($user, $normalizedValues, $originalValueMap, $existingRelations);
     */
    protected function processRelationValues($user, $normalizedValues, $originalValueMap, $existingRelations): array {
        $relationIds = [];

        foreach ($normalizedValues as $type => $typeValues) {
            $relationIds[$type] = [];

            foreach ($typeValues as $normalizedValue) {
                if (isset($existingRelations[$type][$normalizedValue])) {
                    $relationIds[$type][] = $existingRelations[$type][$normalizedValue]->id;
                } else {
                    $relation = new PostAllowedValue();
                    $relation->name = trim($originalValueMap[$type][$normalizedValue]);
                    $relation->type = $type;
                    $relation->created_by_role = $user->role;
                    $relation->created_by_user_id = $user->id;
                    $relation->save();

                    $relationIds[$type][] = $relation->id;

                    $existingRelations[$type][$normalizedValue] = $relation;
                }
            }
        }
        return $relationIds;
    }
}
