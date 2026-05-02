<?php

namespace App\Models;

use App\Services\News\NewsPublicationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
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
        'published_notification_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'published_notification_sent_at' => 'datetime',
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
            if ($news->wasChanged('published_notification_sent_at')) {
                return;
            }

            app(NewsPublicationService::class)->sendPublishedNotificationIfNeeded(
                $news->fresh(),
                Auth::user() instanceof User ? Auth::user() : null,
            );
        });
    }

    public function isDraft(): bool
    {
        return $this->published_at === null;
    }

    public function isScheduled(): bool
    {
        return $this->published_at !== null && $this->published_at->isFuture();
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lessThanOrEqualTo(now());
    }

    public function publicationStatusLabel(): string
    {
        if ($this->isDraft()) {
            return 'مسودة';
        }

        if ($this->isScheduled()) {
            return 'مجدول';
        }

        return 'منشور';
    }

    /**
     * English label for Filament edit header / badges (CMS-style UI).
     */
    public function publicationStatusLabelEn(): string
    {
        if ($this->isDraft()) {
            return 'Draft';
        }

        if ($this->isScheduled()) {
            return 'Scheduled';
        }

        return 'Published';
    }

    /**
     * وصف نصي موجز للوحة الإدارة (ملخص الحالة).
     */
    public function publicationStatusDescription(): string
    {
        if ($this->isDraft()) {
            return 'هذا الخبر غير ظاهر للعامة.';
        }

        if ($this->isScheduled()) {
            return 'سيظهر الخبر تلقائياً عند الموعد المحدد.';
        }

        return 'هذا الخبر ظاهر للعامة.';
    }

    /**
     * ملخص موعد الظهور في لوحة الإدارة (مسودة / مجدول / منشور).
     */
    public function adminVisibilitySummary(): string
    {
        if ($this->isDraft()) {
            return 'لم يتم تحديد موعد ظهور';
        }

        $formatted = $this->published_at?->timezone(config('app.timezone'))->format('Y/m/d H:i');

        if ($this->isScheduled()) {
            return 'مجدول للظهور في: '.$formatted;
        }

        return 'نُشر في: '.$formatted;
    }

    /**
     * عرض نسبي لتاريخ لوحة التحرير (مع الوقت الكامل في title عبر الواجهة).
     */
    public function adminRelativeTime(?Carbon $at): string
    {
        if ($at === null) {
            return '—';
        }

        return $at->timezone(config('app.timezone'))->diffForHumans();
    }

    /**
     * تاريخ/وقت كامل لنفس الحقل أعلاه.
     */
    public function adminFullDateTime(?Carbon $at): string
    {
        if ($at === null) {
            return '—';
        }

        return $at->timezone(config('app.timezone'))->format('Y/m/d H:i');
    }

    /**
     * ملخص موعد الظهور لصف جدول البيانات (نسبي).
     */
    public function adminPublishedAtRelative(): string
    {
        if ($this->isDraft()) {
            return '—';
        }

        return $this->adminRelativeTime($this->published_at);
    }

    /**
     * تاريخ موعد الظهور الكامل (للتلميح).
     */
    public function adminPublishedAtFull(): string
    {
        if ($this->isDraft()) {
            return 'غير محدد';
        }

        return $this->adminFullDateTime($this->published_at);
    }

    /**
     * ملخص حالة تنبيه الوارد لبطاقة تعديل الخبر (نص قصير).
     */
    public function notificationStatusSummary(): string
    {
        if (! $this->isPublished()) {
            return 'لم يُرسل';
        }

        if ($this->published_notification_sent_at !== null) {
            return 'تم الإرسال في '.$this->published_notification_sent_at->timezone(config('app.timezone'))->format('Y/m/d H:i');
        }

        return 'لم يُرسل';
    }

    /**
     * حالة تنبيه الوارد بعد النشر (مرة واحدة لكل دورة نشر).
     */
    public function inboxNotificationStatusLabel(): string
    {
        if (! $this->isPublished()) {
            return 'لا ينطبق — الخبر ليس منشوراً بعد.';
        }

        return $this->published_notification_sent_at !== null
            ? 'تم إرسال تنبيه الوارد عند النشر.'
            : 'لم يُرسل تنبيه الوارد بعد (سيُرسل عند استيفاء شروط الإرسال).';
    }

    /**
     * Filament badge color key.
     */
    public function publicationStatusColor(): string
    {
        return match ($this->publicationStatusLabel()) {
            'منشور' => 'success',
            'مجدول' => 'warning',
            default => 'gray',
        };
    }

    public function shouldSendPublishedNotification(): bool
    {
        return $this->isPublished()
            && $this->published_notification_sent_at === null;
    }

    /**
     * Preview label for live form state (string or Carbon from Filament).
     */
    public static function publicationStatusLabelFromFormState(mixed $publishedAt): string
    {
        if ($publishedAt === null || $publishedAt === '') {
            return 'مسودة';
        }

        try {
            $dt = $publishedAt instanceof Carbon
                ? $publishedAt
                : Carbon::parse($publishedAt instanceof \DateTimeInterface ? $publishedAt : (string) $publishedAt);
        } catch (\Throwable) {
            return 'مسودة';
        }

        if ($dt->isFuture()) {
            return 'مجدول';
        }

        return 'منشور';
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
