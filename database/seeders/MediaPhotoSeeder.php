<?php

namespace Database\Seeders;

use App\Models\MediaPhoto;
use App\Support\MediaPhotoLibrarySupport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

class MediaPhotoSeeder extends Seeder
{
    private const ASSETS_ROOT = 'seeders/assets/media-photos';

    private const STORAGE_PREFIX = 'media/photos/library';

    /** @var list<string> */
    private array $seededImagePaths = [];

    public function run(): void
    {
        if (! Schema::hasTable('media_photos')) {
            $this->command?->warn('MediaPhotoSeeder: table `media_photos` is missing. Run migrations first.');

            return;
        }

        $root = database_path(self::ASSETS_ROOT);

        if (! File::isDirectory($root)) {
            $this->command?->warn('MediaPhotoSeeder: assets directory is missing at '.self::ASSETS_ROOT);

            return;
        }

        Storage::disk('public')->makeDirectory(self::STORAGE_PREFIX);

        $sortOrder = 0;

        foreach (MediaPhotoLibrarySupport::CATEGORIES as $categoryIndex => $category) {
            $categoryPath = $root.DIRECTORY_SEPARATOR.$category;

            if (! File::isDirectory($categoryPath)) {
                continue;
            }

            $sortOrder = $this->seedDirectory(
                $categoryPath,
                category: $category,
                album: $category,
                categoryIndex: $categoryIndex,
                sortOrder: $sortOrder,
            );

            foreach (File::directories($categoryPath) as $albumPath) {
                $albumName = MediaPhotoLibrarySupport::normalizeFolderName(basename($albumPath));

                $sortOrder = $this->seedDirectory(
                    $albumPath,
                    category: $category,
                    album: MediaPhotoLibrarySupport::albumLabel($category, $albumName),
                    categoryIndex: $categoryIndex,
                    sortOrder: $sortOrder,
                );
            }
        }

        $removed = MediaPhoto::query()
            ->where('image', 'like', self::STORAGE_PREFIX.'/%')
            ->whereNotIn('image', $this->seededImagePaths)
            ->delete();

        if ($removed > 0) {
            $this->command?->info("MediaPhotoSeeder: removed {$removed} stale library photo records.");
        }

        $this->command?->info('MediaPhotoSeeder: published '.count($this->seededImagePaths).' photos to the media center.');
    }

    private function seedDirectory(
        string $directory,
        string $category,
        string $album,
        int $categoryIndex,
        int $sortOrder,
    ): int {
        $finder = (new Finder)
            ->files()
            ->in($directory)
            ->depth('== 0')
            ->sortByName()
            ->name('/\.(jpe?g|png|webp)$/i');

        $index = 0;

        foreach ($finder as $file) {
            if (! $file instanceof SplFileInfo) {
                continue;
            }

            $storagePath = $this->publishPhoto($file, $category, $album);

            if ($storagePath === null) {
                continue;
            }

            $this->seededImagePaths[] = $storagePath;

            MediaPhoto::updateOrCreate(
                ['image' => $storagePath],
                [
                    'title' => MediaPhotoLibrarySupport::photoTitle($file->getFilename(), $album, $index),
                    'caption' => MediaPhotoLibrarySupport::photoCaption($category, $album),
                    'category' => $category,
                    'album' => $album,
                    'is_active' => true,
                    'sort_order' => ($categoryIndex * 10_000) + $sortOrder,
                ],
            );

            $sortOrder++;
            $index++;
        }

        return $sortOrder;
    }

    private function publishPhoto(SplFileInfo $file, string $category, string $album): ?string
    {
        $categorySegment = $this->storageSegment($category);
        $albumSegment = $this->storageSegment($album);
        $filename = $this->safeFilename($file->getFilename());

        $relativePath = self::STORAGE_PREFIX.'/'.$categorySegment.'/'.$albumSegment.'/'.$filename;

        Storage::disk('public')->makeDirectory(dirname($relativePath));

        $optimized = $this->optimizedImageBinary($file->getPathname());

        if ($optimized === null) {
            Storage::disk('public')->put($relativePath, File::get($file->getPathname()));

            return $relativePath;
        }

        $relativePath = preg_replace('/\.[^.]+$/', '.jpg', $relativePath) ?? $relativePath.'.jpg';
        Storage::disk('public')->put($relativePath, $optimized);

        return $relativePath;
    }

    /**
     * تصغير وضغط الصورة للويب (بدون تكبير الصور الصغيرة).
     */
    private function optimizedImageBinary(string $path): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $image = $this->loadImage($path);

        if ($image === null) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $maxEdge = 1920;

        if ($width <= 0 || $height <= 0) {
            imagedestroy($image);

            return null;
        }

        $longest = max($width, $height);

        if ($longest > $maxEdge) {
            if ($width >= $height) {
                $newWidth = $maxEdge;
                $newHeight = (int) round($height * ($maxEdge / $width));
            } else {
                $newHeight = $maxEdge;
                $newWidth = (int) round($width * ($maxEdge / $height));
            }
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        ob_start();
        imagejpeg($canvas, null, 82);
        $binary = ob_get_clean() ?: null;
        imagedestroy($canvas);

        return $binary;
    }

    /**
     * @return resource|null
     */
    private function loadImage(string $path)
    {
        $mime = mime_content_type($path) ?: '';

        return match (true) {
            str_contains($mime, 'jpeg') || str_contains($mime, 'jpg') => @imagecreatefromjpeg($path),
            str_contains($mime, 'png') => @imagecreatefrompng($path),
            str_contains($mime, 'webp') => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default => null,
        };
    }

    private function storageSegment(string $label): string
    {
        $slug = Str::slug(trim($label), '-', 'ar');

        if ($slug !== '') {
            return $slug;
        }

        return substr(sha1($label), 0, 12);
    }

    private function safeFilename(string $filename): string
    {
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $slug = Str::slug($basename, '-', 'ar');

        if ($slug === '') {
            $slug = 'photo-'.substr(sha1($filename), 0, 10);
        }

        return $slug.'.jpg';
    }
}
