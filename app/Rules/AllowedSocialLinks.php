<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AllowedSocialLinks implements ValidationRule {
    protected $allowedPlatforms = [
        'github' => 'https://github.com/',
        'linkedin' => 'https://linkedin.com/in/',
    ];

    /**
     * Run the validation rule.
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
        }
    }

    /**
     * Validate if a URL matches the expected pattern
     */
    protected function validateUrl(string $url, string $baseUrl): bool {
        return str_starts_with($url, $baseUrl);
    }
}
