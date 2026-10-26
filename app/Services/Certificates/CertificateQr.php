<?php

namespace App\Services\Certificates;

use Endroid\QrCode\Builder\Builder;

final class CertificateQr
{
    public static function png(string $contents): string
    {
        return self::builder($contents)->build()->getString();
    }

    public static function dataUri(string $contents): string
    {
        return self::builder($contents)->build()->getDataUri();
    }

    private static function builder(string $contents): Builder
    {
        return new Builder(
            data: $contents,
            size: 280,
            margin: 0,
        );
    }
}
