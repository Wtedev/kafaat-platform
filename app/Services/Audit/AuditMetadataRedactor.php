<?php

namespace App\Services\Audit;

use App\Services\Security\SensitiveDataRedactor;

final class AuditMetadataRedactor
{
    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    public static function redact(?array $metadata): ?array
    {
        return SensitiveDataRedactor::redact($metadata);
    }
}
