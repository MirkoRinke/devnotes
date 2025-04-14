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
     * Determines if external sources should be displayed based on user settings and request headers.
     *
     * @param \Illuminate\Http\Request $request The HTTP request instance.
     * @param \App\Models\User $user The user instance.
     * @param string $type The type of external source (e.g., 'images', 'videos', 'resources').
     * @return bool True if external sources should be displayed, false otherwise.
     */
    public function shouldDisplayExternals($request, $user, string $type) {
        // If no user is logged in 
        if (!$user) {
            return $request->header("X-Show-External-" . ucfirst($type)) === 'true';
        }

        // Check permanent setting
        if ($user->profile->{"auto_load_external_{$type}"} === true) {
            return true;
        }

        // Check temporary setting
        if ($user->profile->{"external_{$type}_temp_until"} && now()->lt($user->profile->{"external_{$type}_temp_until"})) {
            return true;
        }

        return false;
    }
}
