<?php

namespace App\Rules;

use Closure;
use App\Models\ForbiddenName;
use Illuminate\Contracts\Validation\ValidationRule;

class NotForbiddenName implements ValidationRule {
    /**
     * Validation rule to prevent registration with forbidden names
     * 
     * This rule is applied during user registration as a first-line defense.
     * It checks if the provided name exists in the forbidden_names table
     * 
     *! Example: admin = forbidden / admin1234 = allowed 
     *! ( partial match Checked by UserModerationService and auto report)
     * 
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void {
        // Check if the name is forbidden
        if (ForbiddenName::whereLike('name', $value)->exists()) {
            $fail('NAME_IS_FORBIDDEN');
            return;
        }
    }
}
