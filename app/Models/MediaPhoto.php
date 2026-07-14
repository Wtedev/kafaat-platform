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
     * Active media-center photos suitable for hero/marketing (no people).
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
     * Public URL for the homepage hero: first people-free library photo, else static fallback.
     */
    public static function homepageHeroUrl(string $fallbackRelativeToPublic = 'images/home/hero.jpg'): string
    {
        $photo = static::query()
            ->withoutPeople()
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
