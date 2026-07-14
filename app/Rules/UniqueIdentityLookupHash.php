<?php

namespace App\Rules;

use App\Enums\IdentityType;
use App\Services\Identity\IdentityNumberService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueIdentityLookupHash implements ValidationRule
{
    public function __construct(
        private readonly ?IdentityType $type,
        private readonly ?int $ignoreUserId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || ! $this->type instanceof IdentityType) {
            return;
        }

        try {
            if (IdentityNumberService::isDuplicate((string) $value, $this->ignoreUserId)) {
                $fail('تعذر إكمال التسجيل بهذه البيانات. يمكنك استخدام استعادة الحساب أو التواصل مع الدعم.');
            }
        } catch (\RuntimeException) {
            $fail('تعذر التحقق من تفرّد رقم الهوية حالياً. يرجى المحاولة لاحقاً.');
        }
    }
}
