<?php

namespace App\Rules;

use App\Models\ForbiddenName;

use App\Traits\CacheHelper;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotForbiddenName implements ValidationRule {
    /**
     *  The traits used in the controller
     */
    use CacheHelper;

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

        $cacheKey = $this->generateSimpleCacheKey('forbidden_names');

        $forbiddenNames = $this->cacheData($cacheKey, 3600, function () {
            return ForbiddenName::pluck('name')->toArray();
        });

        if (in_array($value, $forbiddenNames)) {
            $fail('FORBIDDEN_NAME');
            return;
        }
    }
}
