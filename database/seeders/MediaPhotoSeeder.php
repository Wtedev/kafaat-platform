<?php

namespace Database\Seeders;

use App\Models\MediaPhoto;
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

    /**
     * ترتيب عرض الأقسام الرئيسية في المركز الإعلامي.
     *
     * @var list<string>
     */
    private const CATEGORY_ORDER = [
        'الفعاليات والمبادرات',
        'زيارات واستضافات',
        'مرافق الجمعية',
    ];

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

        foreach (self::CATEGORY_ORDER as $categoryIndex => $category) {
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
                $albumName = trim(basename($albumPath));

                $sortOrder = $this->seedDirectory(
                    $albumPath,
                    category: $category,
                    album: $category.' · '.$albumName,
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

        foreach ($finder as $file) {
            if (! $file instanceof SplFileInfo) {
                continue;
            }

            $storagePath = $this->publishPhoto($file, $category, $album);

            if ($storagePath === null) {
                continue;
            }

            $this->seededImagePaths[] = $storagePath;

            $title = $this->photoTitle($file, $album);

            MediaPhoto::updateOrCreate(
                ['image' => $storagePath],
                [
                    'title' => $title,
                    'caption' => $album,
                    'album' => $album,
                    'is_active' => true,
                    'sort_order' => ($categoryIndex * 10_000) + $sortOrder,
                ],
            );

            $sortOrder++;
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
        Storage::disk('public')->put($relativePath, File::get($file->getPathname()));

        return $relativePath;
    }

    private function photoTitle(SplFileInfo $file, string $album): string
    {
        $base = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        $humanized = Str::of($base)
            ->replace(['_', '-'], ' ')
            ->squish()
            ->value();

        if ($humanized === '' || preg_match('/^(img|dsc|7p0a|unnamed)/i', $humanized)) {
            return Str::before($album, ' ·') ?: $album;
        }

        return $humanized;
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
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $slug = Str::slug($basename, '-', 'ar');

        if ($slug === '') {
            $slug = 'photo-'.substr(sha1($filename), 0, 10);
        }

        return $slug.'.'.$extension;
    }
}
