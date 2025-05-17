<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

use App\Models\ForbiddenName;

use App\Traits\CacheHelper;

/**
 * This NotForbiddenName is used to validate the name provided by the user during registration.
 * It checks if the name exists in the forbidden_names table.
 * 
 * @example | new NotForbiddenName()
 * @example | 'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255', new NotForbiddenName()],     
 */
class NotForbiddenName implements ValidationRule {

    /**
     *  The traits used in the Rule
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
     * @param string $attribute The attribute name
     * @param mixed $value The value to validate
     * @param Closure $fail The closure to call on failure
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
