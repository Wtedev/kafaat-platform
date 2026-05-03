<?php

namespace App\Models;

use App\Enums\LearningPathKind;
use App\Enums\PathStatus;
use App\Enums\RegistrationStatus;
use App\Services\Inbox\InboxNotificationService;
use App\Support\PublicDiskPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LearningPath extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'path_kind',
        'description',
        'image',
        'capacity',
        'status',
        'published_at',
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
            'capacity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $path) {
            if (empty($path->slug)) {
                $path->slug = Str::slug($path->title);
            }

            if ($path->owner_id === null && filled($path->created_by)) {
                $path->owner_id = $path->created_by;
            }
        });

        static::created(function (self $path): void {
            if ($path->status !== PathStatus::Published) {
                return;
            }

            $actor = Auth::user();

            app(InboxNotificationService::class)->learningPathLaunched(
                $path,
                $actor instanceof User ? $actor : null,
            );
        });

        static::updated(function (self $path): void {
            if (! $path->wasChanged('status')) {
                return;
            }

            if ($path->status !== PathStatus::Published) {
                return;
            }

            $actor = Auth::user();

            app(InboxNotificationService::class)->learningPathLaunched(
                $path,
                $actor instanceof User ? $actor : null,
            );
        });
    }

    /** Public URL for catalog image (or placeholder). */
    public function imagePublicUrl(): string
    {
        return PublicDiskPath::urlOrPlaceholder($this->image ?? null, PublicDiskPath::PLACEHOLDER_TRAINING_CATALOG);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePublished(Builder $query): void
    {
        $query->where('status', PathStatus::Published);
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
