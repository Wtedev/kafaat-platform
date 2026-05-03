<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Normalizes paths stored in the database for the Laravel "public" disk
 * and builds correct URLs. Handles legacy values such as:
 * "public/...", "storage/...", "public/storage/...", leading slashes, and full http(s) URLs.
 */
final class PublicDiskPath
{
    /** صورة افتراضية للكتالوج العام (برامج، مسارات) عند غياب صورة مرفوعة. */
    public const PLACEHOLDER_TRAINING_CATALOG = 'images/training-catalog-placeholder.svg';

    /** صورة افتراضية للفرص التطوعية عند غياب صورة مرفوعة. */
    public const PLACEHOLDER_VOLUNTEER_OPPORTUNITY = 'images/volunteer-opportunity-placeholder.svg';

    /**
     * @return string|null Relative path on disk "public", or an absolute http(s) URL as stored.
     */
    public static function normalize(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $p = trim(str_replace('\\', '/', (string) $path));
        if ($p === '') {
            return null;
        }

        if (Str::startsWith($p, ['http://', 'https://'])) {
            return $p;
        }

        while (str_starts_with($p, '/')) {
            $p = substr($p, 1);
        }

        $strip = [
            'public/storage/',
            'storage/storage/',
            'public/',
            'storage/',
        ];

        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($strip as $prefix) {
                if (str_starts_with($p, $prefix)) {
                    $p = substr($p, strlen($prefix));
                    $changed = true;
                }
            }
        }

        return $p !== '' ? $p : null;
    }

    /**
     * Public URL for a file on disk "public", or the remote URL if stored as absolute.
     * Returns null if the relative path does not exist on disk.
     */
    public static function url(?string $path, string $disk = 'public'): ?string
    {
        $n = self::normalize($path);
        if ($n === null) {
            return null;
        }
        if (Str::startsWith($n, ['http://', 'https://'])) {
            return $n;
        }
        if (! Storage::disk($disk)->exists($n)) {
            return null;
        }

        return Storage::disk($disk)->url($n);
    }

    /**
     * Same as {@see url()} but falls back to a local asset (default: news placeholder SVG).
     */
    public static function urlOrPlaceholder(?string $path, ?string $placeholderRelativeToPublic = null): string
    {
        $fallback = asset(ltrim($placeholderRelativeToPublic ?? 'images/news-placeholder.svg', '/'));
        $resolved = self::url($path);
        if ($resolved !== null) {
            return $resolved;
        }

        $n = self::normalize($path);
        if ($n !== null && Str::startsWith($n, ['http://', 'https://'])) {
            return $n;
        }

        return $fallback;
    }
}
