<?php

namespace App\Services\Certificates;

use App\Enums\CertificateFieldKey;
use App\Models\Certificate;

class CertificateSnapshotBuilder
{
    /**
     * @return array<string, string>
     */
    public static function build(Certificate $certificate): array
    {
        $snapshot = [];

        foreach (CertificateFieldKey::cases() as $key) {
            $snapshot[$key->value] = $key->resolve($certificate);
        }

        return $snapshot;
    }
}
