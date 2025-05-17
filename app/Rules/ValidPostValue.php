<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

use App\Models\PostAllowedValue;

use App\Traits\CacheHelper;

/**
 * This ValidPostValue is used to validate the post value provided by the user.
 * It checks if the value exists in the post_allowed_values table.
 * 
 * @example | new ValidPostValue('category')
 * @example | 'category' => ['required', 'string', new ValidPostValue('category')],
 */
class ValidPostValue implements ValidationRule {

    /**
     *  The traits used in the Rule
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
     *  @param string $attribute The name of the attribute being validated (e.g. 'category')
     *  @param mixed $value The value of the attribute being validated (e.g. 'Backend')
     *  @param \Closure $fail The closure to call if validation fails
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void {

        if (!is_string($value)) {
            $type = explode('.', $attribute)[0];
            $fail(strtoupper($type) . '_MUST_BE_STRING');
            return;
        }

        $cacheKey = $this->generateSimpleCacheKey('post_allowed_values_' . $this->type);

        $allowedValues = $this->cacheData($cacheKey, 3600, function () {
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
