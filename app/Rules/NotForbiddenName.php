<?php

namespace App\Rules;

use Closure;
use App\Models\ForbiddenName;
use Illuminate\Contracts\Validation\ValidationRule;

class NotForbiddenName implements ValidationRule {
    /**
     * Run the validation rule.
     *
     * @param string $attribute
     * @param mixed $value
     * @param \Closure $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void {
        if (ForbiddenName::whereLike('name', $value)->exists()) {
            $fail('NAME_IS_FORBIDDEN');
        }
    }
}
