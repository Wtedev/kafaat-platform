<?php

namespace App\Services\Documents;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class PrivateDocumentsStorage
{
    public static function diskName(): string
    {
        $disk = (string) config('cv.private_disk');

        if ($disk === '') {
            throw new RuntimeException('Private documents disk is not configured (PRIVATE_DOCUMENTS_DISK).');
        }

        if (! array_key_exists($disk, config('filesystems.disks', []))) {
            throw new RuntimeException('Configured private documents disk is invalid.');
        }

        return $disk;
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }
}
