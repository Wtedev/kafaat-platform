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
            if (self::isEphemeralUploadUrl($p)) {
                return null;
            }

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

        // رفض اجتياز المسار (path traversal): أي مقطع ".." يُبطل المسار.
        foreach (explode('/', $p) as $segment) {
            if ($segment === '..') {
                return null;
            }
        }

        return $p !== '' ? $p : null;
    }

    /**
     * Detect short-lived Livewire/Filament preview or signed temporary upload URLs.
     * These must never be treated as permanent media paths.
     */
    public static function isEphemeralUploadUrl(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            return false;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $query = (string) (parse_url($url, PHP_URL_QUERY) ?? '');

        if (str_contains($path, '/preview-file/')) {
            return true;
        }

        if (str_contains($path, '/livewire/') && str_contains($path, 'upload')) {
            return true;
        }

        // Livewire / Laravel temporary signed URLs include both expires + signature.
        if ($query !== '' && str_contains($query, 'expires=') && str_contains($query, 'signature=')) {
            return true;
        }

        return false;
    }

    /**
     * Extract the Livewire temp filename from a preview-file URL, if present.
     */
    public static function livewirePreviewFilename(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        if (! preg_match('#/preview-file/([^/]+)$#', $path, $matches)) {
            return null;
        }

        $filename = rawurldecode($matches[1]);

        return $filename !== '' ? $filename : null;
    }

    /**
     * Convert an absolute /storage/... URL (any host) to a public-disk relative path.
     */
    public static function relativePathFromPublicUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        if ($path === '') {
            return null;
        }

        if (! preg_match('#(?:^|/)storage/(.+)$#', $path, $matches)) {
            return null;
        }

        return self::normalize(rawurldecode($matches[1]));
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
            if (self::isEphemeralUploadUrl($n)) {
                return null;
            }

            return $n;
        }
        if (! Storage::disk($disk)->exists($n)) {
            if (file_exists(public_path($n))) {
                return '/'.ltrim($n, '/');
            }

            return null;
        }

        $driver = (string) config("filesystems.disks.{$disk}.driver", 'local');

        // Object storage (S3/R2): use the disk URL (CDN / bucket public URL).
        if ($driver !== 'local') {
            return Storage::disk($disk)->url($n);
        }

        // Relative URL so logos load on the current host/port (avoids APP_URL port mismatches in local dev).
        return '/storage/'.ltrim($n, '/');
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

        return $fallback;
    }
}
