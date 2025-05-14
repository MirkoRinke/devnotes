<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Trait CacheHelper
 *
 * This trait provides methods for caching data and generating cache keys.
 * It includes methods to cache the result of a callback function, generate cache keys,
 * retrieve the cache TTL, and clear the cache for specific model types.
 * 
 */
trait CacheHelper {

    /**
     * Cache the result of a callback function.
     *
     * @param string $cacheKey The cache key to store the result.
     * @param int|null $cacheTTL The time-to-live for the cache in seconds, or null to use default
     * @param \Closure $callback The callback function to execute and cache its result.
     * @return mixed The cached result.
     * 
     * @example | $criticalTerms = $this->cacheData($cacheKey, 3600, function () {
     *              return CriticalTerm::all();
     *            });
     */
    protected function cacheData($cacheKey, $cacheTTL, \Closure $callback) {
        if ($cacheTTL === null) {
            $cacheTTL = $this->getCacheTTL();
        }
        return Cache::remember($cacheKey, $cacheTTL, $callback);
    }

    /**
     * Generate a cache key based on the model type and request parameters.
     *
     * @param string $modelType The type of the model (e.g., 'user', 'post').
     * @param string $parameter The parameter to be included in the cache key.
     * @param Request $request The current request instance.
     * @return string The generated cache key.
     * 
     * @example | $cacheKey = $this->generateCacheKey('user', 'allowed_values', $request);
     */
    protected function generateCacheKey(string $modelType, string $parameter, Request $request): string {
        return strtolower($modelType) . $parameter . md5(json_encode($request->all()));
    }

    /**
     * Generate a simple cache key based on a prefix.
     *
     * @param string $prefix The prefix to be used in the cache key.
     * @return string The generated cache key.
     * 
     * @example | $cacheKey = $this->generateSimpleCacheKey('critical_terms');
     */
    protected function generateSimpleCacheKey(string $prefix): string {
        return strtolower($prefix);
    }

    /**
     * Retrieve the cache TTL (Time To Live) for the cache entries.
     *
     * @return int The cache TTL in seconds.
     * 
     * @example | $ttl = $this->getCacheTTL();
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
     * 
     * @example | $this->forgetCacheByModelType('App\Models\CriticalTerm');
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


    /**
     * Clear the cache for post allowed values of a specific type.
     *
     * @param string $type The type of post allowed values to clear from the cache.
     * @return void
     * 
     * @example | $this->forgetPostAllowedValueCache($postAllowedValue->type);
     */
    protected function forgetPostAllowedValueCache(string $type): void {
        Cache::forget($this->generateSimpleCacheKey('post_allowed_values_' . $type));
    }

    /**
     * Clear the cache for forbidden names.
     *
     * @return void
     * 
     * @example | $this->forgetForbiddenNameCache();
     */
    protected function forgetForbiddenNameCache(): void {
        Cache::forget($this->generateSimpleCacheKey('forbidden_names'));
        Cache::forget($this->generateSimpleCacheKey('forbidden_names_partial'));
    }
}
