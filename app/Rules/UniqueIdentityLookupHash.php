<?php

namespace App\Rules;

use App\Enums\IdentityType;
use App\Services\Identity\IdentityNumberService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueIdentityLookupHash implements ValidationRule
{
    public function __construct(
        private readonly ?IdentityType $type = null,
        private readonly ?int $ignoreUserId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        try {
            // Uniqueness is by normalized number HMAC, independent of identity_type.
            if (IdentityNumberService::isDuplicate((string) $value, $this->ignoreUserId)) {
                $fail(IdentityNumberService::DUPLICATE_MESSAGE);
            }
        } catch (\RuntimeException) {
            $fail('تعذر التحقق من تفرّد رقم الهوية حالياً. يرجى المحاولة لاحقاً.');
        }
    }
}
