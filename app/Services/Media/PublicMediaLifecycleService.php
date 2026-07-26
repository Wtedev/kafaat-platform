<?php

namespace App\Services\Media;

use App\Support\PublicDiskPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Safe replace/remove for staff/public marketing images on the Laravel "public" disk.
 * Never deletes git-backed assets under public/images or shared placeholders.
 */
final class PublicMediaLifecycleService
{
    public const DISK = 'public';

    /** @var list<string> */
    public const OWNED_DIRECTORY_PREFIXES = [
        'news/images/',
        'news/covers/',
        'programs/covers/',
        'programs/images/',
        'volunteer-opportunities/images/',
        'volunteer-opportunities/covers/',
        'learning-paths/images/',
        'avatars/',
        'staff-photos/',
        'profiles/',
    ];

    /**
     * Store an uploaded image under $directory (relative to public disk).
     * Returns the relative path only.
     */
    public function storeUpload(UploadedFile $file, string $directory): string
    {
        $directory = trim(str_replace('\\', '/', $directory), '/');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }

        $filename = Str::uuid()->toString().'.'.$extension;

        return $file->storeAs($directory, $filename, self::DISK);
    }

    /**
     * After a successful DB update: delete the previous owned file if it differs.
     */
    public function deleteOwnedIfReplaced(?string $previousPath, ?string $newPath): void
    {
        $previous = PublicDiskPath::normalize($previousPath);
        $new = PublicDiskPath::normalize($newPath);

        if ($previous === null || $previous === $new) {
            return;
        }

        $this->deleteOwnedPath($previous);
    }

    /**
     * Delete a newly uploaded file after a failed DB write (cleanup orphan).
     */
    public function discardFailedUpload(?string $newPath): void
    {
        $path = PublicDiskPath::normalize($newPath);
        if ($path === null) {
            return;
        }

        $this->deleteOwnedPath($path);
    }

    public function deleteOwnedPath(?string $path): bool
    {
        $normalized = PublicDiskPath::normalize($path);
        if ($normalized === null) {
            return false;
        }

        if (! $this->isOwnedPublicDiskPath($normalized)) {
            return false;
        }

        if (Str::startsWith($normalized, ['http://', 'https://'])) {
            return false;
        }

        // Never delete files that only exist as git public/ assets.
        if (! Storage::disk(self::DISK)->exists($normalized)) {
            return false;
        }

        try {
            return Storage::disk(self::DISK)->delete($normalized);
        } catch (Throwable) {
            return false;
        }
    }

    public function isOwnedPublicDiskPath(string $normalizedPath): bool
    {
        $path = ltrim(str_replace('\\', '/', $normalizedPath), '/');

        if (str_contains($path, '..')) {
            return false;
        }

        // Git-backed marketing assets and placeholders are never owned for deletion.
        if (Str::startsWith($path, ['images/', 'css/', 'js/', 'fonts/'])) {
            return false;
        }

        foreach (self::OWNED_DIRECTORY_PREFIXES as $prefix) {
            if (Str::startsWith($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
