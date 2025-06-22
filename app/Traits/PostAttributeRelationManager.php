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

        $relationData = $this->filterEmptyRelations($relationData, $post, $relationMap);
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

                // Get the pivot table name from the relation map
                $pivotTable = $post->{$relationMap[$type]}()->getTable();

                DB::table($pivotTable)->where('post_id', $post->id)->delete();

                $pivotData = [];
                foreach ($ids as $id) {
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


    /**
     * Filter out empty relations from the provided relation data
     * 
     * @param array $relationData The relation data to filter
     * @param Post $post The post instance
     * @param array $relationMap Mapping of relation types to post relation methods
     * @return array Filtered relation data without empty relations
     * 
     * @example | $filteredData = $this->filterEmptyRelations($relationData, $post, $relationMap);
     */
    protected function filterEmptyRelations($relationData, Post $post, $relationMap): array {
        foreach ($relationData as $type => $values) {
            if (empty($values)) {
                if (isset($relationMap[$type])) {
                    $post->{$relationMap[$type]}()->sync([]);
                }
                unset($relationData[$type]);
            }
        }
        return $relationData;
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
