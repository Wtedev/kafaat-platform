<?php

namespace App\Rules;

use App\Enums\IdentityType;
use App\Services\Identity\IdentityNumberService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidIdentityNumber implements ValidationRule
{
    public function __construct(
        private readonly ?IdentityType $type,
        private readonly bool $required = true,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            if ($this->required) {
                $fail('رقم الهوية أو الإقامة مطلوب.');
            }

            return;
        }

        if (! $this->type instanceof IdentityType) {
            $fail('نوع الهوية مطلوب.');

            return;
        }

        $normalized = IdentityNumberService::normalize(is_string($value) ? $value : (string) $value);

        if ($normalized === null || ! IdentityNumberService::isValidForType($normalized, $this->type)) {
            $fail('رقم الهوية أو الإقامة غير صالح.');
        }
    }
}
