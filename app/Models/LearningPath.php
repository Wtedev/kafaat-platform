<?php

namespace App\Models;

use App\Enums\LearningPathKind;
use App\Enums\PathStatus;
use App\Enums\RegistrationStatus;
use App\Jobs\SendLearningPathLaunchedNotifications;
use App\Models\Concerns\HasEntityNotes;
use App\Models\User;
use App\Support\PublicDiskPath;
use App\Support\UniqueModelSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LearningPath extends Model
{
    use HasEntityNotes;
    protected $fillable = [
        'title',
        'slug',
        'path_kind',
        'description',
        'image',
        'capacity',
        'auto_accept_registrations',
        'status',
        'published_at',
        'notify_on_publish',
        'created_by',
        'owner_id',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'path_kind' => LearningPathKind::class,
            'status' => PathStatus::class,
            'published_at' => 'datetime',
            'notify_on_publish' => 'boolean',
            'capacity' => 'integer',
            'auto_accept_registrations' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $path) {
            if (Auth::check()) {
                $userId = Auth::id();
                if ($path->created_by === null) {
                    $path->created_by = $userId;
                }
                $path->updated_by = $userId;
            }

            if (blank($path->slug) && filled($path->title)) {
                $path->slug = UniqueModelSlug::fromTitle(
                    self::class,
                    $path->title,
                    fallbackPrefix: 'path',
                );
            }

            if ($path->owner_id === null && filled($path->created_by)) {
                $path->owner_id = $path->created_by;
            }

            if ($path->status === PathStatus::Published && $path->published_at === null) {
                $path->published_at = now();
            }
        });

        static::updating(function (self $path): void {
            if (Auth::check()) {
                $path->updated_by = Auth::id();
            }

            if ($path->isDirty('status')) {
                if ($path->status === PathStatus::Published && $path->published_at === null) {
                    $path->published_at = now();
                }
            }

            if ($path->isDirty('slug') && blank($path->slug) && filled($path->title)) {
                $path->slug = UniqueModelSlug::fromTitle(
                    $path,
                    $path->title,
                    fallbackPrefix: 'path',
                    ignoreId: $path->getKey(),
                );
            }
        });

        static::created(function (self $path): void {
            if ($path->status !== PathStatus::Published || ! $path->notify_on_publish) {
                return;
            }

            self::dispatchLearningPathLaunchedNotification($path);
        });

        static::updated(function (self $path): void {
            if (! $path->wasChanged('status')) {
                return;
            }

            if ($path->status !== PathStatus::Published || ! $path->notify_on_publish) {
                return;
            }

            self::dispatchLearningPathLaunchedNotification($path);
        });
    }

    private static function dispatchLearningPathLaunchedNotification(self $path): void
    {
        try {
            $actor = Auth::user();

            SendLearningPathLaunchedNotifications::dispatch(
                $path->id,
                $actor instanceof User ? $actor->id : null,
            )->afterCommit();
        } catch (\Throwable $e) {
            Log::error('تعذّر جدولة تنبيه إطلاق المسار.', [
                'path_id' => $path->getKey(),
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /** Public URL for catalog image (or placeholder). */
    public function imagePublicUrl(): string
    {
        return PublicDiskPath::urlOrPlaceholder($this->image ?? null, PublicDiskPath::PLACEHOLDER_TRAINING_CATALOG);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePublished(Builder $query): void
    {
        $now = now();

        $query->where('status', PathStatus::Published)
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', $now);
            });
    }

    public function scopeDraft(Builder $query): void
    {
        $query->where('status', PathStatus::Draft);
    }

    public function scopeArchived(Builder $query): void
    {
        $query->where('status', PathStatus::Archived);
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function registrations(): HasMany
    {
        return $this->hasMany(PathRegistration::class);
    }

    /**
     * For statistics / withCount — registrations marked completed (مجتازون).
     */
    public function completedPathRegistrations(): HasMany
    {
        return $this->hasMany(PathRegistration::class)
            ->where('status', RegistrationStatus::Completed->value);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function editors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'learning_path_editors')->withTimestamps();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function approvedRegistrationsCount(): int
    {
        return $this->registrations()
            ->where('status', RegistrationStatus::Approved->value)
            ->count();
    }

    public function approvedRegistrations(): HasMany
    {
        return $this->registrations()
            ->where('status', RegistrationStatus::Approved->value);
    }

    public function hasCapacity(): bool
    {
        if ($this->capacity === null) {
            return true;
        }

        return $this->approvedRegistrationsCount() < $this->capacity;
    }

    public function remainingCapacity(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        return max(0, $this->capacity - $this->approvedRegistrationsCount());
    }

    public function isCompletedBy(User $user): bool
    {
        return $this->registrations()
            ->where('user_id', $user->id)
            ->where('status', RegistrationStatus::Completed->value)
            ->exists();
    }

    public function certificates(): MorphMany
    {
        return $this->morphMany(Certificate::class, 'certificateable');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(TrainingProgram::class)
            ->orderByRaw('path_sort_order IS NULL')
            ->orderBy('path_sort_order')
            ->orderBy('id');
    }
}
