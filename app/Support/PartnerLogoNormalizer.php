<?php

namespace App\Support;

/**
 * Ensures partner logo binaries are real PNG files with a transparent matte.
 *
 * Seeded logos were previously stored as JPEG payloads (still named .png) whose
 * transparency had been flattened to solid black by GD/export tooling.
 */
final class PartnerLogoNormalizer
{
    private const BLACK_THRESHOLD = 36;

    private const MAX_EDGE = 720;

    /**
     * Returns a PNG binary when the input needs repair; null when the logo is
     * already a usable transparent (or non-matte) image and should be kept as-is.
     */
    public static function normalizeBinary(string $binary): ?string
    {
        if ($binary === '' || ! extension_loaded('gd')) {
            return null;
        }

        $isJpegPayload = str_starts_with($binary, "\xFF\xD8\xFF");

        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            return null;
        }

        imagesavealpha($image, true);

        if (! imageistruecolor($image)) {
            $trueColor = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagealphablending($trueColor, false);
            imagesavealpha($trueColor, true);
            $transparent = imagecolorallocatealpha($trueColor, 0, 0, 0, 127);
            imagefill($trueColor, 0, 0, $transparent);
            imagecopy($trueColor, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            imagedestroy($image);
            $image = $trueColor;
        }

        $hasBlackMatte = self::looksLikeOpaqueBlackMatte($image);

        if (! $isJpegPayload && ! $hasBlackMatte) {
            imagedestroy($image);

            return null;
        }

        if ($hasBlackMatte) {
            self::floodBlackMatteToTransparent($image);
        }

        $image = self::resizeIfNeeded($image);
        $image = self::cropToOpaqueContent($image);

        ob_start();
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagepng($image, null, 6);
        $out = ob_get_clean() ?: null;
        imagedestroy($image);

        return ($out === false || $out === null || $out === '') ? null : $out;
    }

    /**
     * @param  \GdImage|resource  $image
     */
    private static function looksLikeOpaqueBlackMatte($image): bool
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width < 2 || $height < 2) {
            return false;
        }

        $samples = [
            [0, 0],
            [$width - 1, 0],
            [0, $height - 1],
            [$width - 1, $height - 1],
            [(int) ($width / 2), 0],
            [(int) ($width / 2), $height - 1],
        ];

        $blackCorners = 0;

        foreach ($samples as [$x, $y]) {
            $rgba = self::rgbaAt($image, $x, $y);

            if ($rgba['a'] < 16) {
                return false;
            }

            if (self::isNearlyBlack($rgba['r'], $rgba['g'], $rgba['b'])) {
                $blackCorners++;
            }
        }

        return $blackCorners >= 4;
    }

    /**
     * @param  \GdImage|resource  $image
     */
    private static function floodBlackMatteToTransparent($image): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $visited = array_fill(0, $height, array_fill(0, $width, false));
        $queue = [];
        $head = 0;

        $enqueue = static function (int $x, int $y) use (&$queue, $width, $height, &$visited): void {
            if ($x < 0 || $y < 0 || $x >= $width || $y >= $height || $visited[$y][$x]) {
                return;
            }
            $queue[] = [$x, $y];
        };

        for ($x = 0; $x < $width; $x++) {
            $enqueue($x, 0);
            $enqueue($x, $height - 1);
        }
        for ($y = 0; $y < $height; $y++) {
            $enqueue(0, $y);
            $enqueue($width - 1, $y);
        }

        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);

        while ($head < count($queue)) {
            [$x, $y] = $queue[$head++];

            if ($visited[$y][$x]) {
                continue;
            }
            $visited[$y][$x] = true;

            $rgba = self::rgbaAt($image, $x, $y);

            if (! self::isNearlyBlack($rgba['r'], $rgba['g'], $rgba['b'])) {
                continue;
            }

            imagesetpixel($image, $x, $y, $transparent);

            $enqueue($x - 1, $y);
            $enqueue($x + 1, $y);
            $enqueue($x, $y - 1);
            $enqueue($x, $y + 1);
        }
    }

    /**
     * @param  \GdImage|resource  $image
     * @return \GdImage|resource
     */
    private static function resizeIfNeeded($image)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= self::MAX_EDGE) {
            return $image;
        }

        $scale = self::MAX_EDGE / $longest;
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefill($resized, 0, 0, $transparent);
        imagealphablending($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagedestroy($image);

        return $resized;
    }

    /**
     * @param  \GdImage|resource  $image
     * @return \GdImage|resource
     */
    private static function cropToOpaqueContent($image)
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (self::rgbaAt($image, $x, $y)['a'] >= 16) {
                    $minX = min($minX, $x);
                    $minY = min($minY, $y);
                    $maxX = max($maxX, $x);
                    $maxY = max($maxY, $y);
                }
            }
        }

        if ($maxX < $minX || $maxY < $minY) {
            return $image;
        }

        $pad = 12;
        $minX = max(0, $minX - $pad);
        $minY = max(0, $minY - $pad);
        $maxX = min($width - 1, $maxX + $pad);
        $maxY = min($height - 1, $maxY + $pad);

        $cropW = $maxX - $minX + 1;
        $cropH = $maxY - $minY + 1;

        if ($cropW === $width && $cropH === $height) {
            return $image;
        }

        $cropped = imagecreatetruecolor($cropW, $cropH);
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        $transparent = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
        imagefill($cropped, 0, 0, $transparent);
        imagecopy($cropped, $image, 0, 0, $minX, $minY, $cropW, $cropH);
        imagedestroy($image);

        return $cropped;
    }

    /**
     * @param  \GdImage|resource  $image
     * @return array{r: int, g: int, b: int, a: int} Alpha 0–255 (PNG style).
     */
    private static function rgbaAt($image, int $x, int $y): array
    {
        $color = imagecolorat($image, $x, $y);
        $a = ($color & 0x7F000000) >> 24;

        return [
            'r' => ($color >> 16) & 0xFF,
            'g' => ($color >> 8) & 0xFF,
            'b' => $color & 0xFF,
            // GD alpha: 0 opaque … 127 transparent → convert to 0–255 PNG alpha
            'a' => (int) round((127 - $a) / 127 * 255),
        ];
    }

    private static function isNearlyBlack(int $r, int $g, int $b): bool
    {
        return $r <= self::BLACK_THRESHOLD
            && $g <= self::BLACK_THRESHOLD
            && $b <= self::BLACK_THRESHOLD;
    }
}
