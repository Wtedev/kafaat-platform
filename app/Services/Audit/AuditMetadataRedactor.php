<?php

namespace App\Services\Audit;

final class AuditMetadataRedactor
{
    private const SENSITIVE_KEYS = [
        'path',
        'disk',
        'signed_url',
        'identity',
        'identity_number',
        'otp',
        'token',
        'password',
        'ciphertext',
        'lookup_hash',
        'original_filename',
    ];

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    public static function redact(?array $metadata): ?array
    {
        if ($metadata === null || $metadata === []) {
            return $metadata;
        }

        $redacted = [];
        foreach ($metadata as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                continue;
            }

            if (is_array($value)) {
                $redacted[$key] = self::redact($value);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }
}
