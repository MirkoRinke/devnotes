<?php

namespace App\Services;

class ExternalSourcePreviewsService {

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
}
