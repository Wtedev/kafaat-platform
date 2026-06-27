<?php

namespace App\Rules;

use App\Services\Identity\SaudiPhoneService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidSaudiMobile implements ValidationRule
{
    public function __construct(
        private readonly bool $required = true,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            if ($this->required) {
                $fail('رقم الجوال مطلوب.');
            }

            return;
        }

        if (! SaudiPhoneService::isValid(is_string($value) ? $value : (string) $value)) {
            $fail('رقم الجوال غير صالح. استخدم رقماً سعودياً يبدأ بـ 05.');
        }
    }
}
