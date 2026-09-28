<?php

namespace App\Support\Media;

use App\Support\PublicDiskPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Picks a fill color from an image's edges so letterboxed program covers
 * sit on a matching surface instead of a generic gray frame.
 */
final class ImageSurfaceColor
{
    public const FALLBACK = '#eef2f6';

    public static function fromStoredPath(?string $path, string $fallback = self::FALLBACK): string
    {
        $file = self::resolveFile($path);
        if ($file === null) {
            return $fallback;
        }

        $mtime = @filemtime($file);
        $cacheKey = 'image-surface-color:'.md5($file.':'.(string) $mtime);

        try {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && preg_match('/^#[0-9a-f]{6}$/i', $cached) === 1) {
                return strtolower($cached);
            }

            $sampled = self::sample($file) ?? $fallback;
            Cache::put($cacheKey, $sampled, now()->addDays(30));

            return $sampled;
        } catch (Throwable) {
            return $fallback;
        }
    }

    private static function resolveFile(?string $path): ?string
    {
        $normalized = PublicDiskPath::normalize($path);
        if ($normalized === null) {
            return null;
        }

        $publicFile = public_path($normalized);
        if (is_file($publicFile)) {
            return $publicFile;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($normalized)) {
            $resolved = $disk->path($normalized);

            return is_file($resolved) ? $resolved : null;
        }

        return null;
    }

    private static function sample(string $file): ?string
    {
        $image = self::open($file);
        if ($image === null) {
            return null;
        }

        try {
            $width = imagesx($image);
            $height = imagesy($image);
            if ($width < 2 || $height < 2) {
                return null;
            }

            $insetX = max(1, (int) floor($width * 0.01));
            $insetY = max(1, (int) floor($height * 0.01));
            $midX = intdiv($width, 2);
            $midY = intdiv($height, 2);

            $points = [
                [$insetX, $insetY],
                [$width - $insetX - 1, $insetY],
                [$insetX, $height - $insetY - 1],
                [$width - $insetX - 1, $height - $insetY - 1],
                [$midX, $insetY],
                [$midX, $height - $insetY - 1],
                [$insetX, $midY],
                [$width - $insetX - 1, $midY],
            ];

            $counts = [];
            foreach ($points as [$x, $y]) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 255;
                $g = ($rgb >> 8) & 255;
                $b = $rgb & 255;
                $key = sprintf('%d:%d:%d', intdiv($r, 16), intdiv($g, 16), intdiv($b, 16));
                $counts[$key]['n'] = ($counts[$key]['n'] ?? 0) + 1;
                $counts[$key]['r'] = ($counts[$key]['r'] ?? 0) + $r;
                $counts[$key]['g'] = ($counts[$key]['g'] ?? 0) + $g;
                $counts[$key]['b'] = ($counts[$key]['b'] ?? 0) + $b;
            }

            uasort($counts, static fn (array $a, array $b): int => $b['n'] <=> $a['n']);
            $winner = reset($counts);
            if (! is_array($winner) || ($winner['n'] ?? 0) < 1) {
                return null;
            }

            $n = (int) $winner['n'];

            return sprintf(
                '#%02x%02x%02x',
                (int) round($winner['r'] / $n),
                (int) round($winner['g'] / $n),
                (int) round($winner['b'] / $n),
            );
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * @return \GdImage|null
     */
    private static function open(string $file): mixed
    {
        $mime = @mime_content_type($file) ?: '';

        $image = match (true) {
            str_contains($mime, 'jpeg') || str_contains($mime, 'jpg') => @imagecreatefromjpeg($file),
            str_contains($mime, 'png') => @imagecreatefrompng($file),
            str_contains($mime, 'webp') && function_exists('imagecreatefromwebp') => @imagecreatefromwebp($file),
            default => null,
        };

        return $image === false ? null : $image;
    }
}
