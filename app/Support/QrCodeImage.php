<?php

namespace App\Support;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

final class QrCodeImage
{
    /**
     * PNG data-URI ready for an &lt;img src&gt;.
     */
    public static function dataUri(string $payload, int $size = 280): string
    {
        $result = (new Builder(
            writer: new PngWriter,
            writerOptions: [],
            validateResult: false,
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 12,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build();

        return $result->getDataUri();
    }
}
