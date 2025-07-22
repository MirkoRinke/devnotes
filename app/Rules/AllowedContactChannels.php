<?php

namespace App\Rules;

use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * This AllowedContactChannels is used to validate the contact channels provided by the user.
 * It checks if the contact channels are allowed and ensures that the values are NOT URLs or web domains.
 * 
 * @example | new AllowedContactChannels()
 * @example | 'contact_channels' => ['sometimes', 'nullable', 'array', new AllowedContactChannels()]
 */
class AllowedContactChannels implements ValidationRule {

    /**
     * The allowed contact channels
     */
    protected $allowedChannels = [
        'discord',
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
        foreach ($value as $channel => $contactValue) {

            if (!in_array($channel, $this->allowedChannels)) {
                $fail("CONTACT_CHANNEL_TYPE_NOT_ALLOWED");
                return;
            }

            $urlValidator = Validator::make(['contact' => $contactValue], [
                'contact' => 'url'
            ]);

            $containsWebDomain = $this->looksLikeDomain($contactValue);

            if ($urlValidator->passes() || $containsWebDomain) {
                $fail("CONTACT_CHANNEL_CONTAINS_URL");
                return;
            }
        }
    }

    /**
     * Detects if a string resembles a web domain.
     *
     * This method checks if the input could be interpreted as a domain name,
     * even if it doesn't have a protocol prefix (http://, https://, etc.).
     *
     * @param string $value The string to check
     * @return bool True if it looks like a domain, false otherwise
     * 
     * @example | $this->looksLikeDomain('example.com')
     */
    protected function looksLikeDomain(string $value): bool {
        $testUrl = (strpos($value, '://') === false) ? 'http://' . $value : $value;

        $parsed = parse_url($testUrl);

        return isset($parsed['host']) && strpos($parsed['host'], '.') !== false;
    }
}
