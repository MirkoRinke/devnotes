<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * This SafeUrl class is used to validate URLs provided by the user.
 * It checks if the URLs are safe and ensures that the values are URLs with the correct format.
 * 
 * @example | new SafeUrl()
 * @example | 'images.*' => ['max:2048', new SafeUrl()],
 */
class SafeUrl implements ValidationRule {

    /**
     * Run the validation rule.
     * 
     * @param string $attribute The attribute name
     * @param mixed $value The value to validate
     * @param Closure $fail The closure to call on failure
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void {
        $this->isValidUrl($value, $fail);
    }


    /**
     * Validate if the URL is safe
     * 
     * @param mixed $value The value to validate
     * @param Closure $fail The closure to call on failure
     * @return bool True if the URL is valid, false otherwise
     * 
     * @example | $this->isValidUrl($value, $fail);
     */
    public function isValidUrl(mixed $value, Closure $fail): bool {
        if (!$this->hasValidProtocol($value, $fail)) return false;

        $parsed = parse_url($value);

        if ($parsed === false) {
            $fail("UNSAFE_URL_PROTOCOL");
            return false;
        }

        if (!$this->hasValidHost($parsed, $fail)) return false;
        if (!$this->hasValidContent($parsed, $fail)) return false;

        return true;
    }

    /**
     * Check if the URL uses an allowed protocol
     * 
     * @param string $value The URL to check
     * @param Closure $fail The closure to call on failure
     * @return bool True if the protocol is valid, false otherwise
     * 
     * @example | $this->hasValidProtocol($value, $fail);
     */
    private function hasValidProtocol(string $value, Closure $fail): bool {
        if (strpos($value, '://') === false) {
            $fail("URL_REQUIRES_PROTOCOL");
            return false;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'])) {
            $fail("UNSAFE_URL_PROTOCOL");
            return false;
        }

        return true;
    }

    /**
     * Check if the URL has a valid host
     * 
     * @param array $parsed The parsed URL components
     * @param Closure $fail The closure to call on failure
     * @return bool True if the host is valid, false otherwise
     * 
     * @example | $this->hasValidHost($parsed, $fail);
     */
    private function hasValidHost(array $parsed, Closure $fail): bool {
        if (!isset($parsed['host']) || strpos($parsed['host'], '.') === false) {
            $fail("UNSAFE_URL_PROTOCOL");
            return false;
        }
        return true;
    }

    /**
     * Check if the URL content (path/query) contains dangerous characters
     * 
     * @param array $parsed The parsed URL components
     * @param Closure $fail The closure to call on failure
     * @return bool True if the content is valid, false otherwise
     * 
     * @example | $this->hasValidContent($parsed, $fail);
     */
    private function hasValidContent(array $parsed, Closure $fail): bool {
        $dangerousCharacters = ['<', '>', '"', "'", '\\', '`'];

        /**
         * Extract path and query components
         */
        $pathToCheck = isset($parsed['path']) ? $parsed['path'] : '';
        if (isset($parsed['query'])) {
            $pathToCheck .= '?' . $parsed['query'];
        }

        /**
         * Check for dangerous characters
         */
        foreach ($dangerousCharacters as $char) {
            if (strpos($pathToCheck, $char) !== false) {
                $fail("UNSAFE_URL_CONTENT");
                return false;
            }
        }
        return true;
    }
}
