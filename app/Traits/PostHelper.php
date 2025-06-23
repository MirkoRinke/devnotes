<?php

namespace App\Traits;

use Illuminate\Http\Request;

use App\Models\Post;

trait PostHelper {

    /**
     * Generate external source previews for post data
     * 
     * @param array $validatedData The validated post data
     * @param Post|null $existingPost Existing post for fallback values (null for creation)
     * @return array The generated external source previews
     */
    protected function generateExternalSourcePreviews(array $validatedData, ?Post $existingPost = null): array {
        if (array_key_exists('images', $validatedData) || array_key_exists('resources', $validatedData) || array_key_exists('videos', $validatedData)) {
            $externalSourcePreviews = $this->externalSourceService->generatePreviews([
                'images' => $validatedData['images'] ?? $existingPost?->images ?? [],
                'videos' => $validatedData['videos'] ?? $existingPost?->videos ?? [],
                'resources' => $validatedData['resources'] ?? $existingPost?->resources ?? []
            ]);

            return $externalSourcePreviews;
        }

        return $existingPost->external_source_previews ?? [];
    }
}
