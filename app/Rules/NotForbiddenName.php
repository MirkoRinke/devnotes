<?php

namespace App\Rules;

use Closure;
use App\Models\ForbiddenName;
use Illuminate\Contracts\Validation\ValidationRule;

class NotForbiddenName implements ValidationRule {
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void {
        // Check if the name is forbidden exactly
        if (ForbiddenName::where('match_type', 'exact')->whereLike('name', $value)->exists()) {
            $fail('NAME_IS_FORBIDDEN');
            return;
        }

        // Check if the name contains a forbidden word
        $partialMatches = ForbiddenName::where('match_type', 'partial')->get(['name']);
        foreach ($partialMatches as $forbidden) {
            if (stripos($value, $forbidden->name) !== false) {
                $fail('NAME_CONTAINS_FORBIDDEN_WORD');
                return;
            }
        }
    }
}
