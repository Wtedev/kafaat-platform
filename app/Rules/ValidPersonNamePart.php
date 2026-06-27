<?php

namespace App\Rules;

use App\Services\Identity\PersonNameService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPersonNamePart implements ValidationRule
{
    public function __construct(
        private readonly bool $required = true,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            if ($this->required) {
                $fail('هذا الحقل مطلوب.');
            }

            return;
        }

        if (! PersonNameService::isValidPart(is_string($value) ? $value : (string) $value)) {
            $fail('صيغة الاسم غير صحيحة. استخدم حروفاً عربية أو إنجليزية فقط.');
        }
    }
}
