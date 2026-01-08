<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string): void  $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Check minimum length
        if (strlen($value) < 8) {
            $fail('The :attribute must be at least 8 characters.');
            return;
        }

        // Check for uppercase letter
        if (!preg_match('/[A-Z]/', $value)) {
            $fail('The :attribute must contain at least 1 uppercase letter.');
            return;
        }

        // Check for lowercase letter
        if (!preg_match('/[a-z]/', $value)) {
            $fail('The :attribute must contain at least 1 lowercase letter.');
            return;
        }

        // Check for number
        if (!preg_match('/[0-9]/', $value)) {
            $fail('The :attribute must contain at least 1 number.');
            return;
        }

        // Check for special character
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $value)) {
            $fail('The :attribute must contain at least 1 special character (!@#$%^&*(),.?":{}|<>).');
            return;
        }
    }
}
