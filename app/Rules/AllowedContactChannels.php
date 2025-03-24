<?php

namespace App\Rules;

use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Validation\ValidationRule;

class AllowedContactChannels implements ValidationRule {
    protected $allowedChannels = [
        'discord',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void {
        foreach ($value as $channel => $contactValue) {

            if (!in_array($channel, $this->allowedChannels)) {
                $fail("The contact channel '$channel' is not allowed. Allowed channels are: " . implode(', ', $this->allowedChannels));
                return;
            }

            // Check if the value is a URL
            $urlValidator = Validator::make(['contact' => $contactValue], [
                'contact' => 'url'
            ]);

            // Check if the value looks like a domain
            $containsWebDomain = $this->looksLikeDomain($contactValue);

            // If the value is a URL or contains a web domain, fail the validation
            if ($urlValidator->passes() || $containsWebDomain) {
                $fail("The value for '$channel' should not contain a URL or web link.");
                return;
            }
        }
    }

    protected function looksLikeDomain(string $value): bool {
        // Check if the value looks like a domain
        $testUrl = (strpos($value, '://') === false) ? 'http://' . $value : $value;
        // Parse the URL and check if it has a host with a dot
        $parsed = parse_url($testUrl);
        // Check if the host has a dot in it
        return isset($parsed['host']) && strpos($parsed['host'], '.') !== false;
    }
}
