<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\MediaPhoto;
use App\Models\News;
use App\Support\MediaPhotoLibrarySupport;
use Illuminate\Support\Collection;

class PublicMediaController extends Controller
{
    public function __invoke()
    {
        $news = News::published()
            ->latest('published_at')
            ->paginate(12, ['*'], 'news_page');

        $photos = MediaPhoto::active()
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get()
            ->filter(function (MediaPhoto $photo): bool {
                $category = filled($photo->category) ? (string) $photo->category : 'عام';

                return MediaPhotoLibrarySupport::isAllowedAlbum($category, $photo->album);
            })
            ->values();

        $photoSections = $this->groupPhotosByCategoryAndAlbum($photos);

        return view('public.media', compact('news', 'photos', 'photoSections'));
    }

    /**
     * @param  Collection<int, MediaPhoto>  $photos
     * @return Collection<string, Collection<string, Collection<int, MediaPhoto>>>
     */
    private function groupPhotosByCategoryAndAlbum(Collection $photos): Collection
    {
        $grouped = $photos->groupBy(function (MediaPhoto $photo): string {
            return filled($photo->category) ? (string) $photo->category : 'عام';
        });

        $ordered = collect();

        foreach (MediaPhotoLibrarySupport::CATEGORIES as $category) {
            if (! $grouped->has($category)) {
                continue;
            }

            $ordered->put(
                $category,
                $this->groupAndSortAlbums($grouped->get($category))
            );
        }

        foreach ($grouped as $category => $categoryPhotos) {
            if ($ordered->has($category)) {
                continue;
            }

            $ordered->put($category, $this->groupAndSortAlbums($categoryPhotos));
        }

        return $ordered;
    }

    /**
     * @param  Collection<int, MediaPhoto>  $categoryPhotos
     * @return Collection<string, Collection<int, MediaPhoto>>
     */
    private function groupAndSortAlbums(Collection $categoryPhotos): Collection
    {
        return $categoryPhotos
            ->groupBy(function (MediaPhoto $photo): string {
                return MediaPhotoLibrarySupport::normalizeFolderName((string) ($photo->album ?? 'عام'));
            })
            ->sortByDesc(fn (Collection $albumPhotos, string $album): int => MediaPhotoLibrarySupport::albumSortTimestamp($album))
            ->map(fn (Collection $albumPhotos): Collection => $albumPhotos->values());
    }
}
