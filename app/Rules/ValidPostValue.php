<?php

namespace App\Rules;

use App\Models\PostAllowedValue;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;


class ValidPostValue implements ValidationRule {

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

        // Check if the value exists in the post_allowed_values table for the given type
        //!TODO Implement file cache to improve performance
        $exists = PostAllowedValue::where([
            'name' => $value,
            'type' => $this->type
        ])->exists();

        // If the value does not exist, fail the validation
        if (!$exists) {
            $fail('VALUE_IS_FORBIDDEN');
            return;
        }
    }
}
