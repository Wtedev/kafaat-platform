<?php

namespace App\Models;

use App\Services\Inbox\InboxNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class News extends Model
{
    protected $table = 'news';

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'image',
        'category',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $news) {
            if (empty($news->slug)) {
                $base = Str::slug($news->title);
                if (empty($base)) {
                    $base = 'news-'.Str::random(6);
                }
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $news->slug = $slug;
            }
        });

        static::saved(function (self $news): void {
            if (! $news->wasChanged('published_at')) {
                return;
            }

            $publishedAt = $news->published_at;
            if ($publishedAt === null || $publishedAt->isFuture()) {
                return;
            }

            $publisher = Auth::user();
            app(InboxNotificationService::class)->newsPublished(
                $news,
                $publisher instanceof User ? $publisher : null,
            );
        });
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeDraft(Builder $query): void
    {
        $query->whereNull('published_at');
    }

    public function scopeScheduled(Builder $query): void
    {
        $query->whereNotNull('published_at')
            ->where('published_at', '>', now());
    }
}
