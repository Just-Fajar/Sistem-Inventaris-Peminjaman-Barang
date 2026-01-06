<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Check minimum length
        if (strlen($value) < 8) {
            $fail('password.min_length', 'Password harus minimal 8 karakter.');
            return;
        }

        // Check for uppercase letter
        if (!preg_match('/[A-Z]/', $value)) {
            $fail('password.uppercase', 'Password harus mengandung minimal 1 huruf besar.');
            return;
        }

        // Check for lowercase letter
        if (!preg_match('/[a-z]/', $value)) {
            $fail('password.lowercase', 'Password harus mengandung minimal 1 huruf kecil.');
            return;
        }

        // Check for number
        if (!preg_match('/[0-9]/', $value)) {
            $fail('password.number', 'Password harus mengandung minimal 1 angka.');
            return;
        }

        // Check for special character
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $value)) {
            $fail('password.special', 'Password harus mengandung minimal 1 karakter spesial (!@#$%^&*(),.?":{}|<>).');
            return;
        }
    }
}
