<?php

namespace App\Services\Identity;

use App\Enums\IdentityType;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class IdentityNumberService
{
    public const MASK = '******';

    public const DUPLICATE_MESSAGE = 'رقم الهوية مستخدم مسبقاً.';

    public const AVAILABILITY_CHECK_FAILED_MESSAGE = 'تعذر التحقق من تفرّد رقم الهوية حالياً. يرجى المحاولة لاحقاً.';

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
     * National ID / iqama must be exactly 10 numeric digits.
     * No external verification service and no checksum/type-prefix rules.
     */
    public static function isValidFormat(string $normalized): bool
    {
        return (bool) preg_match('/^\d{10}$/', $normalized);
    }

    public static function isValidForType(string $normalized, IdentityType $type): bool
    {
        return self::isValidFormat($normalized);
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
     *     identity_type: IdentityType,
     *     identity_number_ciphertext: string,
     *     identity_number_lookup_hash: string,
     *     identity_number_last4: string,
     *     identity_confirmed_at: \Illuminate\Support\Carbon,
     * }
     */
    public static function prepareStoragePayload(string $rawNumber, IdentityType $type): array
    {
        $normalized = self::normalize($rawNumber);

        if ($normalized === null || ! self::isValidFormat($normalized)) {
            throw new \InvalidArgumentException('Invalid identity number: must be exactly 10 digits.');
        }

        return [
            'identity_type' => $type,
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
