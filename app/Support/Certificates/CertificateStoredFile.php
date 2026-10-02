<?php

namespace App\Support\Certificates;

use App\Support\PublicDiskPath;
use Illuminate\Support\Facades\Storage;

/**
 * ملفات PDF الصادرة تُحفظ على القرص الخاص.
 * الملفات القديمة على القرص العام تبقى قابلة للتنزيل عبر المتحكم إلى أن يُنقل أمر certificates:relocate-private.
 */
final class CertificateStoredFile
{
    public const DISK = 'local';

    public static function put(string $relative, string $contents): void
    {
        Storage::disk(self::DISK)->put($relative, $contents);
    }

    public static function relative(?string $path): ?string
    {
        $relative = PublicDiskPath::normalize($path);
        if ($relative === null || str_starts_with($relative, 'http://') || str_starts_with($relative, 'https://')) {
            return null;
        }

        return $relative;
    }

    public static function diskFor(?string $path): ?string
    {
        $relative = self::relative($path);
        if ($relative === null) {
            return null;
        }

        foreach ([self::DISK, 'public'] as $disk) {
            if (Storage::disk($disk)->exists($relative)) {
                return $disk;
            }
        }

        return null;
    }

    public static function get(?string $path): ?string
    {
        $relative = self::relative($path);
        $disk = self::diskFor($path);
        if ($relative === null || $disk === null) {
            return null;
        }

        $bytes = Storage::disk($disk)->get($relative);

        return is_string($bytes) ? $bytes : null;
    }
}
