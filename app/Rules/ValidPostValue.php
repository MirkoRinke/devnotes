<?php

namespace App\Rules;

use App\Models\PostAllowedValue;

use App\Traits\CacheHelper;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;


class ValidPostValue implements ValidationRule {

    /**
     *  The traits used in the controller
     */
    use CacheHelper;

    /**
     *  The type of post value to validate against
     *  e.g. 'language', 'category', 'post_type', 'technology' ,'status'     * 
     */
    protected $type;

    /**
     *  Constructor to set the type of post value
     * 
     * @param string $type The type of post value to validate against
     */
    public function __construct(string $type) {
        $this->type = $type;
    }

    /**
     *  Validation rule to check if the value exists in the post_allowed_values table
     * 
     *  This rule is applied to ensure that the provided value exists in the allowed values for the given type.
     * 
     *  @param string $attribute The name of the attribute being validated
     *  @param mixed $value The value of the attribute being validated
     *  @param \Closure $fail The closure to call if validation fails
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void {

        // Check if the value is empty
        if (!is_string($value)) {
            $type = explode('.', $attribute)[0];
            $fail(strtoupper($type) . '_MUST_BE_STRING');
            return;
        }


        $cacheKey = $this->generateSimpleCacheKey('post_allowed_values_' . $this->type);

        $allowedValues = $this->cacheData($cacheKey, 3600, function () {
            // dd('Fetching allowed values from the database...');
            return PostAllowedValue::where('type', $this->type)
                ->pluck('name')
                ->toArray();
        });

        if (!in_array($value, $allowedValues)) {
            $fail('VALUE_IS_FORBIDDEN');
            return;
        }
    }
}
