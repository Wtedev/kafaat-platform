<?php

namespace App\Services\Security;

final class SensitiveDataRedactor
{
    private const FORBIDDEN_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'otp',
        'code',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
        'cookie',
        'session',
        'identity_number',
        'identity_number_ciphertext',
        'identity_number_lookup_hash',
        'cv_path',
        'signed_url',
        'reset_token',
        'ciphertext',
        'lookup_hash',
        'path',
        'disk',
        'recipient_email',
        'subject',
        'user_agent',
        'ip_address',
        'authorization_header',
        'cookie_header',
        'session_id',
        'payload',
        'remember_token',
        'original_filename',
    ];

    private const MAX_DEPTH = 6;

    private const MAX_KEYS = 50;

    private const MAX_STRING_LENGTH = 500;

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    public static function redact(?array $metadata, int $depth = 0): ?array
    {
        if ($metadata === null || $metadata === []) {
            return $metadata;
        }

        if ($depth >= self::MAX_DEPTH) {
            return ['truncated' => true];
        }

        $redacted = [];
        $count = 0;

        foreach ($metadata as $key => $value) {
            if ($count >= self::MAX_KEYS) {
                $redacted['_truncated_keys'] = true;
                break;
            }

            if (self::isForbiddenKey((string) $key)) {
                continue;
            }

            if (is_array($value)) {
                $redacted[$key] = self::redact($value, $depth + 1);
            } elseif (is_string($value)) {
                $redacted[$key] = mb_strlen($value) > self::MAX_STRING_LENGTH
                    ? mb_substr($value, 0, self::MAX_STRING_LENGTH).'…'
                    : $value;
            } else {
                $redacted[$key] = $value;
            }

            $count++;
        }

        return $redacted;
    }

    public static function isForbiddenKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::FORBIDDEN_KEYS as $forbidden) {
            if ($normalized === $forbidden || str_contains($normalized, $forbidden)) {
                return true;
            }
        }

        return false;
    }
}
