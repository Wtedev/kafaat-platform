<?php

namespace App\Services\Identity;

use App\Enums\IdentityCategory;
use App\Enums\IdentityType;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class IdentityNumberService
{
    public const MASK = '******';

    public const DUPLICATE_MESSAGE = 'رقم الهوية مستخدم مسبقاً.';

    public const AVAILABILITY_CHECK_FAILED_MESSAGE = 'تعذر التحقق من تفرّد رقم الهوية حالياً. يرجى المحاولة لاحقاً.';

    public const INVALID_FORMAT_MESSAGE = 'رقم الهوية أو الإقامة يجب أن يكون 10 أرقام.';

    public const INVALID_PREFIX_MESSAGE = 'رقم الهوية يجب أن يبدأ بـ 1 للهوية الوطنية أو 2 للإقامة.';

    private static bool $reportedLookupKeyFallback = false;

    /**
     * Resolve HMAC material for identity_number_lookup_hash.
     *
     * Prefers IDENTITY_LOOKUP_KEY. When missing, falls back to a stable
     * APP_KEY-derived secret so uniqueness checks still work against the DB
     * (with a critical log for operators). Throws only when both are unavailable.
     */
    public static function lookupKey(): string
    {
        $key = config('identity.lookup_key');

        if (is_string($key) && trim($key) !== '') {
            return $key;
        }

        $appKey = config('app.key');

        if (is_string($appKey) && trim($appKey) !== '') {
            if (! self::$reportedLookupKeyFallback) {
                self::$reportedLookupKeyFallback = true;
                Log::critical(
                    'IDENTITY_LOOKUP_KEY is not configured; using APP_KEY-derived fallback for identity lookup hashes. Set IDENTITY_LOOKUP_KEY in environment secrets to avoid hash drift if APP_KEY is rotated.'
                );
            }

            return hash_hmac('sha256', 'kafaat|identity-lookup-key|v1', $appKey);
        }

        throw new RuntimeException(
            'IDENTITY_LOOKUP_KEY is not configured and APP_KEY is unavailable; cannot compute identity lookup hashes.'
        );
    }

    public static function hasDedicatedLookupKey(): bool
    {
        $key = config('identity.lookup_key');

        return is_string($key) && trim($key) !== '';
    }

    /**
     * @internal Reset process-local fallback notice (tests).
     */
    public static function resetLookupKeyFallbackNotice(): void
    {
        self::$reportedLookupKeyFallback = false;
    }

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = SaudiPhoneService::convertDigitsToLatin($value);
        $digits = preg_replace('/\D+/', '', $digits) ?? '';

        return $digits === '' ? null : $digits;
    }

    /**
     * Derive saudi / resident from the first digit of a normalized 10-digit number.
     * Returns null when the number is not 10 digits or does not start with 1 or 2.
     */
    public static function categoryFromNumber(?string $normalized): ?IdentityCategory
    {
        if ($normalized === null || ! preg_match('/^\d{10}$/', $normalized)) {
            return null;
        }

        return match ($normalized[0]) {
            '1' => IdentityCategory::Saudi,
            '2' => IdentityCategory::Resident,
            default => null,
        };
    }

    /**
     * Exactly 10 digits starting with 1 (national ID) or 2 (iqama).
     */
    public static function isValidFormat(string $normalized): bool
    {
        return self::categoryFromNumber($normalized) !== null;
    }

    public static function isValidForType(string $normalized, IdentityType $type): bool
    {
        $category = self::categoryFromNumber($normalized);

        return $category !== null && $category->toIdentityType() === $type;
    }

    public static function typeFromNumber(string $normalized): ?IdentityType
    {
        return self::categoryFromNumber($normalized)?->toIdentityType();
    }

    /**
     * Arabic validation message for an invalid normalized candidate.
     */
    public static function validationMessage(?string $normalized): string
    {
        if ($normalized === null || ! preg_match('/^\d{10}$/', $normalized)) {
            return self::INVALID_FORMAT_MESSAGE;
        }

        if (self::categoryFromNumber($normalized) === null) {
            return self::INVALID_PREFIX_MESSAGE;
        }

        return self::INVALID_FORMAT_MESSAGE;
    }

    public static function generateLookupHash(string $normalized): string
    {
        return hash_hmac('sha256', $normalized, self::lookupKey());
    }

    public static function lastFour(string $normalized): string
    {
        return substr($normalized, -4);
    }

    public static function mask(?string $lastFour): ?string
    {
        if ($lastFour === null || $lastFour === '') {
            return null;
        }

        return self::MASK.$lastFour;
    }

    public static function encrypt(string $normalized): string
    {
        return Crypt::encryptString($normalized);
    }

    public static function decrypt(string $ciphertext): string
    {
        try {
            return Crypt::decryptString($ciphertext);
        } catch (DecryptException $e) {
            throw new RuntimeException('Unable to decrypt identity number.', 0, $e);
        }
    }

    /**
     * @return array{
     *     identity_category: IdentityCategory,
     *     identity_type: IdentityType,
     *     identity_number_ciphertext: string,
     *     identity_number_lookup_hash: string,
     *     identity_number_last4: string,
     *     identity_confirmed_at: Carbon,
     * }
     */
    public static function prepareStoragePayload(string $rawNumber, ?IdentityType $type = null): array
    {
        $normalized = self::normalize($rawNumber);

        if ($normalized === null || ! preg_match('/^\d{10}$/', $normalized)) {
            throw new InvalidArgumentException(self::INVALID_FORMAT_MESSAGE);
        }

        $category = self::categoryFromNumber($normalized);

        if ($category === null) {
            throw new InvalidArgumentException(self::INVALID_PREFIX_MESSAGE);
        }

        $derivedType = $category->toIdentityType();

        if ($type instanceof IdentityType && $type !== $derivedType) {
            throw new InvalidArgumentException(
                'نوع الهوية لا يطابق الرقم: يجب أن يطابق الرقم المشتق من الخانة الأولى.'
            );
        }

        return [
            'identity_category' => $category,
            'identity_type' => $derivedType,
            'identity_number_ciphertext' => self::encrypt($normalized),
            'identity_number_lookup_hash' => self::generateLookupHash($normalized),
            'identity_number_last4' => self::lastFour($normalized),
            'identity_confirmed_at' => now(),
        ];
    }

    public static function isDuplicate(string $rawNumber, ?int $ignoreUserId = null): bool
    {
        $normalized = self::normalize($rawNumber);

        if ($normalized === null || ! self::isValidFormat($normalized)) {
            return false;
        }

        $hash = self::generateLookupHash($normalized);

        $query = User::query()->where('identity_number_lookup_hash', $hash);

        if ($ignoreUserId !== null) {
            $query->where('id', '!=', $ignoreUserId);
        }

        return $query->exists();
    }

    /**
     * Detect unique-index races on identity_number_lookup_hash (MySQL / PostgreSQL / SQLite).
     */
    public static function isLookupHashUniqueViolation(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        if (! str_contains($message, 'identity_number_lookup_hash')) {
            return false;
        }

        $sqlState = (string) ($exception->errorInfo[0] ?? '');

        return in_array($sqlState, ['23000', '23505'], true)
            || str_contains($message, 'UNIQUE constraint failed');
    }

    /**
     * Extension point for future staff audit logging when viewing full identity.
     */
    public static function recordAuthorizedFullViewAttempt(User $viewer, User $subject): void
    {
        // Reserved for phase 4 audit integration.
    }
}
