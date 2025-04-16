<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

trait CacheHelper {

    /**
     * Cache the result of a callback function.
     *
     * @param string $cacheKey The cache key to store the result.
     * @param \Closure $callback The callback function to execute and cache its result.
     * @return mixed The cached result.
     */
    protected function cacheData($cacheKey, \Closure $callback) {
        $cacheTTL = $this->getCacheTTL();
        return Cache::remember($cacheKey, $cacheTTL, $callback);
    }

    /**
     * Generate a cache key based on the model type and request parameters.
     *
     * @param string $modelType The type of the model (e.g., 'user', 'post').
     * @param string $parameter The parameter to be included in the cache key.
     * @param Request $request The current request instance.
     * @return string The generated cache key.
     */
    protected function generateCacheKey(string $modelType, string $parameter, Request $request): string {
        return strtolower($modelType) . $parameter . md5(json_encode($request->all()));
    }

    /**
     * Retrieve the cache TTL (Time To Live) for the cache entries.
     *
     * @return int The cache TTL in seconds.
     */
    protected function getCacheTTL(): int {
        return 150; // Time to live in seconds (2.5 minutes)
    }

    /**
     * Clear the cache for a specific model type by recursively searching
     * through all cache files for occurrences of the model class name.
     *
     * @param string $modelClass The fully qualified class name of the model (e.g. "App\Models\Post").
     * @return void
     */
    protected function forgetCacheByModelType($modelClass): void {
        $cacheDir = storage_path('framework/cache/data');

        if (!file_exists($cacheDir)) {
            return;
        }

        /**
         * Recursively iterate through the cache directory and its subdirectories
         * to find and delete cache files that contain the model class name.
         */
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $content = file_get_contents($file->getPathname());
                if (strpos($content, $modelClass) !== false) {
                    @unlink($file->getPathname());
                }
            }
        }
    }
}
