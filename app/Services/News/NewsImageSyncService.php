<?php

namespace App\Services\News;

use App\Models\News;
use App\Models\NewsImage;
use App\Support\PublicDiskPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use League\Flysystem\UnableToCheckFileExistence;

final class NewsImageSyncService
{
    private const PUBLIC_DIRECTORY = 'news/images';

    public function purgeFilesForNews(News $news): void
    {
        $paths = $news->images()->pluck('path')->all();

        if ($paths === [] && filled($news->image)) {
            $paths = [$news->image];
        }

        $this->deletePaths($paths);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function sync(News $news, array $rows, bool $allowEmpty = false): void
    {
        $normalized = $this->normalizeRows($rows);

        if ($normalized === [] && ! $allowEmpty && $news->images()->exists()) {
            return;
        }

        DB::transaction(function () use ($news, $normalized): void {
            $existing = $news->images()->get()->keyBy('id');
            $keptIds = [];
            $pathsToDelete = [];

            foreach ($normalized as $index => $row) {
                $path = $this->resolvePath($row['path'] ?? null);
                if ($path === null) {
                    continue;
                }

                $id = isset($row['id']) ? (int) $row['id'] : null;

                if ($id !== null && $existing->has($id)) {
                    /** @var NewsImage $model */
                    $model = $existing->get($id);

                    if ($model->path !== $path) {
                        $pathsToDelete[] = $model->path;
                    }

                    $model->update([
                        'path' => $path,
                        'is_primary' => $row['is_primary'],
                        'sort_order' => $index,
                    ]);
                    $keptIds[] = $model->id;

                    continue;
                }

                $model = $news->images()->create([
                    'path' => $path,
                    'is_primary' => $row['is_primary'],
                    'sort_order' => $index,
                ]);
                $keptIds[] = $model->id;
            }

            foreach ($existing as $id => $model) {
                if (! in_array($id, $keptIds, true)) {
                    $pathsToDelete[] = $model->path;
                    $model->delete();
                }
            }

            $this->deletePaths($pathsToDelete);
            $this->ensurePrimary($news);
            $news->syncPrimaryImageColumn();
        });
    }

    public function migrateLegacyImageIfNeeded(News $news): void
    {
        if ($news->images()->exists()) {
            return;
        }

        if (! filled($news->image)) {
            return;
        }

        $news->images()->create([
            'path' => $news->image,
            'is_primary' => true,
            'sort_order' => 0,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{id: int|null, path: string, is_primary: bool}>
     */
    public function normalizeRows(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $path = $this->resolvePath($row['path'] ?? null);
            if ($path === null) {
                continue;
            }

            $out[] = [
                'id' => isset($row['id']) ? (int) $row['id'] : null,
                'path' => $path,
                'is_primary' => (bool) ($row['is_primary'] ?? false),
            ];
        }

        $primaryIndex = null;

        foreach ($out as $index => $row) {
            if (! $row['is_primary']) {
                continue;
            }

            if ($primaryIndex === null) {
                $primaryIndex = $index;

                continue;
            }

            $out[$index]['is_primary'] = false;
        }

        if ($primaryIndex === null && $out !== []) {
            $out[0]['is_primary'] = true;
        }

        return $out;
    }

    /**
     * @return array<int, array{id: int|null, path: string, is_primary: bool}>
     */
    public function rowsFromNews(News $news): array
    {
        $this->migrateLegacyImageIfNeeded($news);

        return $news->images()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (NewsImage $image): array => [
                'id' => $image->id,
                'path' => $image->path,
                'is_primary' => $image->is_primary,
            ])
            ->all();
    }

    public function primaryImagePath(News $news): ?string
    {
        $this->migrateLegacyImageIfNeeded($news);

        $primaryPath = $news->images()
            ->where('is_primary', true)
            ->value('path');

        if (filled($primaryPath)) {
            return $primaryPath;
        }

        return filled($news->image) ? $news->image : null;
    }

    private function ensurePrimary(News $news): void
    {
        $images = $news->images()->orderBy('sort_order')->get();

        if ($images->isEmpty()) {
            $news->updateQuietly(['image' => null]);

            return;
        }

        if ($images->contains(fn (NewsImage $image): bool => $image->is_primary)) {
            return;
        }

        $images->first()?->update(['is_primary' => true]);
    }

    private function resolvePath(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = $path[0] ?? null;
        }

        if ($path instanceof TemporaryUploadedFile) {
            return $this->persistTemporaryUpload($path);
        }

        if (! is_string($path) || blank($path)) {
            return null;
        }

        $path = trim($path);

        if (Str::startsWith($path, 'livewire-file:')) {
            return $this->persistTemporaryUpload(
                TemporaryUploadedFile::createFromLivewire(Str::after($path, 'livewire-file:'))
            );
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $this->resolveHttpPath($path);
        }

        $normalized = PublicDiskPath::normalize($path);
        if ($normalized === null) {
            return null;
        }

        if ($this->isLivewireTemporaryRelativePath($normalized)) {
            return $this->persistTemporaryUpload(
                TemporaryUploadedFile::createFromLivewire(basename($normalized))
            );
        }

        if (! $this->publicDiskExists($normalized)) {
            return null;
        }

        return $normalized;
    }

    private function resolveHttpPath(string $url): ?string
    {
        if (PublicDiskPath::isEphemeralUploadUrl($url)) {
            $filename = PublicDiskPath::livewirePreviewFilename($url);
            if ($filename === null) {
                return null;
            }

            return $this->persistTemporaryUpload(
                TemporaryUploadedFile::createFromLivewire($filename)
            );
        }

        $relative = PublicDiskPath::relativePathFromPublicUrl($url);
        if ($relative !== null && $this->publicDiskExists($relative)) {
            return $relative;
        }

        // Durable external absolute URLs (CDN / remote assets) are kept as-is.
        return $url;
    }

    private function persistTemporaryUpload(TemporaryUploadedFile $file): ?string
    {
        try {
            if (! $file->exists()) {
                return null;
            }

            $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg'));
            $extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?: 'jpg';
            $filename = (string) Str::ulid().'.'.$extension;

            $stored = $file->storeAs(self::PUBLIC_DIRECTORY, $filename, 'public');
            if (! is_string($stored) || blank($stored)) {
                return null;
            }

            rescue(fn () => Storage::disk('public')->setVisibility($stored, 'public'), report: false);

            try {
                $file->delete();
            } catch (\Throwable) {
                // Temp cleanup is best-effort; the permanent public copy already exists.
            }

            return $stored;
        } catch (\Throwable) {
            return null;
        }
    }

    private function publicDiskExists(string $path): bool
    {
        try {
            return Storage::disk('public')->exists($path);
        } catch (UnableToCheckFileExistence) {
            return false;
        }
    }

    private function isLivewireTemporaryRelativePath(string $path): bool
    {
        return Str::startsWith($path, ['livewire-tmp/', 'livewire-tmp\\'])
            || str_contains($path, '/livewire-tmp/')
            || str_contains($path, '\\livewire-tmp\\');
    }

    /**
     * @param  array<int, string|null>  $paths
     */
    private function deletePaths(array $paths): void
    {
        foreach ($paths as $path) {
            if (! is_string($path) || blank($path)) {
                continue;
            }

            if (Str::startsWith($path, ['http://', 'https://'])) {
                continue;
            }

            if (NewsImage::query()->where('path', $path)->exists()) {
                continue;
            }

            if (News::query()->where('image', $path)->exists()) {
                continue;
            }

            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
