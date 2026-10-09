<?php

namespace App\Rules;

use App\Enums\IdentityType;
use App\Services\Identity\IdentityNumberService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidIdentityNumber implements ValidationRule
{
    public function __construct(
        private readonly ?IdentityType $type = null,
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

        $normalized = IdentityNumberService::normalize(is_string($value) ? $value : (string) $value);

        if ($normalized === null || ! IdentityNumberService::isValidFormat($normalized)) {
            $fail(IdentityNumberService::validationMessage($normalized));

            return;
        }

        if ($this->type instanceof IdentityType
            && ! IdentityNumberService::isValidForType($normalized, $this->type)) {
            $fail(IdentityNumberService::INVALID_PREFIX_MESSAGE);
        }
    }
}
