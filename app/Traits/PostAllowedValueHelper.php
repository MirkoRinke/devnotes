<?php

namespace App\Traits;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use App\Models\Post;
use App\Models\PostAllowedValue;

/**
 * PostAllowedValueHelper
 *
 * This trait provides helper methods for working with Post Allowed Values.
 */
trait PostAllowedValueHelper {

    /**
     * Check if the Post Allowed Value is used in any posts
     * 
     * @param string $name The name of the allowed value
     * @param string $type The type of the allowed value (e.g., category, post_type, status)
     * @return bool True if the value is in use, false otherwise
     * 
     * @example | $isInUse = $this->isPostAllowedValueInUse($name, $type)
     */
    protected function isPostAllowedValueInUse($name, $type) {
        $isInUse = false;

        switch ($type) {
            case 'category':
                $isInUse = Post::where('category', $name)->exists();
                break;
            case 'post_type':
                $isInUse = Post::where('post_type', $name)->exists();
                break;
            case 'status':
                $isInUse = Post::where('status', $name)->exists();
                break;
        }
        return $isInUse;
    }


    /**
     * Update the post count for specific allowed values
     * 
     * Increments or decrements the post_count for matching PostAllowedValues.
     * Uses case-insensitive comparison to ensure consistent counting.
     * 
     * @param string|array $names The name(s) of the allowed values to update
     * @param string $type The type of the allowed values (category, post_type, status, tag, language, technology)
     * @param string $method The update method to use ('increment' or 'decrement')
     * @return void
     * 
     * @example | $this->updatePostAllowedValueCount(['PHP', 'JavaScript'], 'language', 'increment');
     */
    protected function updatePostAllowedValueCount($names, $type, $method) {
        if (!is_array($names)) {
            $names = [$names];
        }

        if (empty($names)) {
            return;
        }

        $query = PostAllowedValue::where('type', $type)
            ->whereIn(DB::raw('LOWER(TRIM(name))'), $names);

        $query->$method('post_count');
    }

    /**
     * Synchronize post allowed value counts when a post is created or updated
     * 
     * Compares old and new values to determine which counts should be incremented or decremented.
     * Handles both direct fields (category, post_type, status) and relations (tags, languages, technologies).
     * Uses case-insensitive comparison to ensure consistent counting.
     * 
     * @param array $syncValidatedData The validated data containing the new values
     * @param Post|null $post The post being updated (null for new posts)
     * @param array|null $oldRelations The old relations data for comparison (used in update operations)
     * @return void
     * 
     * @example | $this->syncPostAllowedValueCounts($validatedData, $oldPost, $oldRelations);
     */
    protected function syncPostAllowedValueCounts($syncValidatedData, $post = null, $oldRelations = null) {
        $postAllowedValueMap = ["category", "post_type", "status", "tag", "language", "technology"];
        foreach ($postAllowedValueMap as $value) {
            if (isset($syncValidatedData[$value]) && ($value == 'category' || $value == 'post_type' || $value == 'status')) {

                $newValues = is_array($syncValidatedData[$value]) ? $syncValidatedData[$value] : [$syncValidatedData[$value]];
                $oldValues = $post ? (is_array($post->$value) ? $post->$value : [$post->$value]) : [];

                $newValues = array_map('strtolower', array_map('trim', $newValues));
                $oldValues = array_map('strtolower', array_map('trim', $oldValues));

                $incrementValues = $post ? array_diff($newValues, $oldValues) : $newValues;
                $decrementValues = $post ? array_diff($oldValues, $newValues) : [];

                $this->updatePostAllowedValueCount($incrementValues, $value, 'increment');
                $this->updatePostAllowedValueCount($decrementValues, $value, 'decrement');
            } else if (isset($syncValidatedData[$value]) && $syncValidatedData[$value] !== null && ($value == 'tag' || $value == 'language' || $value == 'technology')) {

                $relationMap = [
                    'tag' => 'tags',
                    'language' => 'languages',
                    'technology' => 'technologies'
                ];
                $relation = $relationMap[$value];

                $newValues = $syncValidatedData[$value];
                $oldValues = $oldRelations && $oldRelations[$relationMap[$value]] !== null
                    ? $oldRelations[$relationMap[$value]]
                    : ($post ? $post->$relation->pluck('name')->toArray() : []);

                $newValues = array_map('strtolower', array_map('trim', $newValues));
                $oldValues = array_map('strtolower', array_map('trim', $oldValues));

                $incrementValues = array_diff($newValues, $oldValues);
                $decrementValues = array_diff($oldValues, $newValues);

                $this->updatePostAllowedValueCount($incrementValues, $value, 'increment');
                $this->updatePostAllowedValueCount($decrementValues, $value, 'decrement');
            }
        }
    }

    /**
     * Decrement post allowed value counts when a post is deleted
     * 
     * Reduces the post_count for all allowed values associated with the post being deleted.
     * Handles both direct fields (category, post_type, status) and relations (tags, languages, technologies).
     * 
     * @param Post $post The post being deleted
     * @return void
     * 
     * @example | $this->destroyPostAllowedValueCounts($post);
     */
    protected function destroyPostAllowedValueCounts($post) {
        $postAllowedValueMap = ["category", "post_type", "status", "tag", "language", "technology"];
        foreach ($postAllowedValueMap as $value) {
            if ($value == 'category' || $value == 'post_type' || $value == 'status') {
                $name = is_array($post->$value) ? $post->$value : [$post->$value];
                $this->updatePostAllowedValueCount($name, $value, 'decrement');
            } else if ($value == 'tag' || $value == 'language' || $value == 'technology') {
                $relationMap = [
                    'tag' => 'tags',
                    'language' => 'languages',
                    'technology' => 'technologies'
                ];
                $relation = $relationMap[$value];
                $names = $post->$relation->pluck('name')->toArray();

                $this->updatePostAllowedValueCount($names, $value, 'decrement');
            }
        }
    }


    /**
     * Format a value based on its type for consistent storage and comparison
     * 
     * Applies specific formatting rules based on the type of the value (e.g., tag, language, technology).
     * For tags: replaces spaces with hyphens and converts to lowercase.
     * 
     * For languages and technologies: converts to PascalCase (e.g., "JavaScript" becomes "JavaScript", "Node js" becomes "NodeJs") to ensure consistent formatting.
     * Optionally forces auto-formatting for languages and technologies to ensure consistent formatting.
     * 
     * @param string $type The type of the value (tag, language, technology)
     * @param string $value The value to format
     * @param bool $autoFormat Whether to force auto-formatting for languages and technologies (default: true)
     * @return string The formatted value
     */
    protected function formatValueByType(string $type, string $value, bool $autoFormat = true): string {
        if ($type === 'tag') {
            $value = str_replace(' ', '-', $value);
            return strtolower($value);
        }

        if ($type === 'language' || $type === 'technology') {
            if ($autoFormat) {
                $pascalCased = str_replace(' ', '', ucwords(strtolower($value)));
                $parts = explode('-', $pascalCased);
                $processedParts = array_map('ucfirst', $parts);
                return implode('-', $processedParts);
            } else {
                return str_replace(' ', '', $value);
            }
        }

        return $value;
    }
}
