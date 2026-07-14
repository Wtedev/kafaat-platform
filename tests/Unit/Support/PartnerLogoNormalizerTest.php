<?php

namespace Tests\Unit\Support;

use App\Support\PartnerLogoNormalizer;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('gd')]
class PartnerLogoNormalizerTest extends TestCase
{
    public function test_converts_jpeg_black_matte_to_transparent_png(): void
    {
        $jpeg = $this->makeBlackMatteJpegLogo();

        $this->assertTrue(str_starts_with($jpeg, "\xFF\xD8\xFF"));

        $png = PartnerLogoNormalizer::normalizeBinary($jpeg);

        $this->assertNotNull($png);
        $this->assertTrue(str_starts_with($png, "\x89PNG\r\n\x1a\n"));

        $image = imagecreatefromstring($png);
        $this->assertNotFalse($image);

        imagesavealpha($image, true);
        $corner = imagecolorat($image, 0, 0);
        $alpha = ($corner & 0x7F000000) >> 24;
        $this->assertGreaterThan(100, $alpha, 'Corner should be transparent after normalization');

        imagedestroy($image);
    }

    public function test_leaves_already_transparent_png_unchanged(): void
    {
        $png = $this->makeTransparentPngLogo();

        $this->assertNull(PartnerLogoNormalizer::normalizeBinary($png));
    }

    private function makeBlackMatteJpegLogo(): string
    {
        $image = imagecreatetruecolor(120, 80);
        $black = imagecolorallocate($image, 0, 0, 0);
        $teal = imagecolorallocate($image, 26, 147, 153);
        imagefill($image, 0, 0, $black);
        imagefilledrectangle($image, 30, 20, 90, 60, $teal);

        ob_start();
        imagejpeg($image, null, 90);
        $binary = ob_get_clean() ?: '';
        imagedestroy($image);

        return $binary;
    }

    private function makeTransparentPngLogo(): string
    {
        $image = imagecreatetruecolor(80, 60);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        $teal = imagecolorallocatealpha($image, 26, 147, 153, 0);
        imagefill($image, 0, 0, $transparent);
        imagefilledrectangle($image, 20, 15, 60, 45, $teal);

        ob_start();
        imagepng($image);
        $binary = ob_get_clean() ?: '';
        imagedestroy($image);

        return $binary;
    }
}
