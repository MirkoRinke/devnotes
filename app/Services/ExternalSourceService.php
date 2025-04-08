<?php

namespace App\Services;

class ExternalSourceService {

    /**
     * Generates preview data for external sources.
     *
     * @param array $fields An associative array where keys are field names and values are arrays of URLs.
     * @return array|null An array of preview data or null if no valid URLs are found.
     */
    public function generatePreviews(array $fields): ?array {
        $previewData = [];

        foreach ($fields as $fieldName => $urls) {
            if (!is_array($urls)) {
                continue;
            }

            foreach ($urls as $url) {
                $previewData[] = [
                    'type' => $fieldName,
                    'url' => $url,
                    'domain' => parse_url($url, PHP_URL_HOST)
                ];
            }
        }

        return !empty($previewData) ? $previewData : null;
    }

    /**
     * Check if external images should be displayed for a user
     * 
     * @param mixed $user The user to check permissions for
     * @return bool True if images should be displayed, false otherwise
     */
    public function shouldDisplayExternalImages($request, $user) {
        // If no user is logged in 
        if (!$user) {
            return $request->header('X-Show-External-Images') === 'true';
        }

        // Check permanent setting
        if ($user->profile->auto_load_external_images) {
            return true;
        }

        // Check temporary setting
        if ($user->profile->external_images_temp_until && now()->lt($user->profile->external_images_temp_until)) {
            return true;
        }

        return false;
    }
}
