<?php

namespace App\Rules;

use App\Enums\IdentityType;
use App\Services\Identity\IdentityNumberService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        } catch (QueryException $exception) {
            Log::error('Identity uniqueness DB check failed.', [
                'attribute' => $attribute,
                'sql_state' => $exception->errorInfo[0] ?? null,
                'exception' => $exception::class,
                'dedicated_lookup_key' => IdentityNumberService::hasDedicatedLookupKey(),
            ]);

            $fail(IdentityNumberService::AVAILABILITY_CHECK_FAILED_MESSAGE);
        } catch (Throwable $exception) {
            Log::error('Identity uniqueness check failed unexpectedly.', [
                'attribute' => $attribute,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'dedicated_lookup_key' => IdentityNumberService::hasDedicatedLookupKey(),
            ]);

            $fail(IdentityNumberService::AVAILABILITY_CHECK_FAILED_MESSAGE);
        }
    }
}
