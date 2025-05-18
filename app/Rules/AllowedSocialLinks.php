<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * This AllowedSocialLinks is used to validate the social links provided by the user.
 * It checks if the social links are allowed and ensures that the values are URLs with the correct format.
 * 
 * @example | new AllowedSocialLinks()
 * @example | 'social_links' => ['sometimes', 'nullable', 'array', new AllowedSocialLinks()],
 */
class AllowedSocialLinks implements ValidationRule {

    /**
     * The allowed platforms for social links
     */
    protected $allowedPlatforms = [
        'github' => 'https://github.com/',
        'linkedin' => 'https://linkedin.com/in/',
    ];

    /**
     * Run the validation rule.
     * 
     * @param string $attribute The attribute name
     * @param mixed $value The value to validate
     * @param Closure $fail The closure to call on failure
     * 
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void {
        foreach ($value as $platform => $url) {
            if (!array_key_exists($platform, $this->allowedPlatforms)) {
                $fail("SOCIAL_LINK_PLATFORM_NOT_ALLOWED");
                return;
            }

            $baseUrl = $this->allowedPlatforms[$platform];
            if (!$this->validateUrl($url, $baseUrl)) {
                $fail("SOCIAL_LINK_INVALID_FORMAT");
                return;
            }

            $safeUrlValidator = new SafeUrl();
            if (!$safeUrlValidator->isValidUrl($url, $fail)) {
                return;
            }
        }
    }

    /**
     * Validate if a URL matches the expected pattern
     * 
     * @param string $url The URL to validate
     * @param string $baseUrl The base URL to check against
     * 
     * @return bool True if the URL starts with the base URL, false otherwise
     * 
     * @example | $this->validateUrl($url, $baseUrl)
     */
    protected function validateUrl(string $url, string $baseUrl): bool {
        return str_starts_with($url, $baseUrl);
    }
}
