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
        // Check if the name is forbidden
        if (ForbiddenName::whereLike('name', $value)->exists()) {
            $fail('NAME_IS_FORBIDDEN');
            return;
        }
    }
}
