<?php

namespace App\Models;

use App\Support\MediaPhotoLibrarySupport;
use App\Support\PublicDiskPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MediaPhoto extends Model
{
    protected $fillable = [
        'title',
        'caption',
        'category',
        'image',
        'album',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Active media-center photos suitable for people-free marketing (facility interiors).
     *
     * Preference: facility category «مرافق الجمعية», then exclude albums whose
     * names match known people-centric event/visit keywords.
     */
    public function scopeWithoutPeople(Builder $query): void
    {
        $query->active()
            ->where('category', MediaPhotoLibrarySupport::PEOPLE_FREE_CATEGORY);

        foreach (MediaPhotoLibrarySupport::PEOPLE_CENTRIC_ALBUM_KEYWORDS as $keyword) {
            $query->where(function (Builder $albumQuery) use ($keyword): void {
                $albumQuery->whereNull('album')
                    ->orWhere('album', 'not like', '%'.$keyword.'%');
            });
        }
    }

    /**
     * Active photos from the homepage hero album («يوم الشباب»).
     */
    public function scopeForHomepageHero(Builder $query): void
    {
        $query->active()
            ->where('album', MediaPhotoLibrarySupport::HOMEPAGE_HERO_ALBUM);
    }

    /**
     * Public URL for the homepage hero: Youth Day library photo, else static fallback.
     *
     * Prefers the curated basename within «يوم الشباب», then any photo in that album
     * (professional Media Center look wins over the people-free facility rule).
     */
    public static function homepageHeroUrl(string $fallbackRelativeToPublic = 'images/home/hero.jpg'): string
    {
        $preferred = MediaPhotoLibrarySupport::HOMEPAGE_HERO_PREFERRED_BASENAME;

        $photo = static::query()
            ->forHomepageHero()
            ->orderByRaw('CASE WHEN LOWER(image) LIKE ? THEN 0 ELSE 1 END', ['%'.$preferred.'%'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($photo !== null) {
            $url = PublicDiskPath::url($photo->image);

            if ($url !== null) {
                return $url;
            }
        }

        return asset(ltrim($fallbackRelativeToPublic, '/'));
    }

    public function imagePublicUrl(): string
    {
        return PublicDiskPath::urlOrPlaceholder($this->image ?? null);
    }
}
