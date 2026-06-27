<?php

namespace App\Services\Identity;

use App\Enums\IdentityType;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class IdentityNumberService
{
    public const MASK = '******';

    public static function lookupKey(): string
    {
        $key = config('identity.lookup_key');

        if (! is_string($key) || trim($key) === '') {
            throw new RuntimeException('IDENTITY_LOOKUP_KEY is not configured.');
        }

        return $key;
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

    public static function isValidChecksum(string $normalized): bool
    {
        if (! preg_match('/^\d{10}$/', $normalized)) {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $digit = (int) $normalized[$i];

            if ($i % 2 === 0) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        return (int) $normalized[9] === $checkDigit;
    }

    public static function isValidForType(string $normalized, IdentityType $type): bool
    {
        if (! self::isValidChecksum($normalized)) {
            return false;
        }

        return $normalized[0] === $type->expectedFirstDigit();
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

        if ($normalized === null || ! self::isValidForType($normalized, $type)) {
            throw new \InvalidArgumentException('Invalid identity number for the selected type.');
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

        if ($normalized === null) {
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
     * Extension point for future staff audit logging when viewing full identity.
     */
    public static function recordAuthorizedFullViewAttempt(User $viewer, User $subject): void
    {
        // Reserved for phase 4 audit integration.
    }
}
