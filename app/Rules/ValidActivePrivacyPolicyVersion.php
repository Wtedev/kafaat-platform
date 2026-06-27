<?php

namespace App\Rules;

use App\Services\Privacy\PrivacyPolicyService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidActivePrivacyPolicyVersion implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $active = PrivacyPolicyService::active();

        if ($active === null || (string) $value !== $active->version) {
            $fail('تم تحديث سياسة الخصوصية، يرجى الاطلاع على النسخة الجديدة ثم تأكيد الإقرار.');
        }
    }
}
