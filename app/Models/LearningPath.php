<?php

namespace App\Models;

use App\Enums\PathStatus;
use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LearningPath extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'capacity',
        'status',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
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
        });
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

    public function courses(): HasMany
    {
        return $this->hasMany(PathCourse::class)->orderBy('sort_order');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(PathRegistration::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
        return $this->hasMany(TrainingProgram::class);
    }

    /**
     * Return a keyed collection of a user's course progress rows for this path.
     * Keyed by path_course_id for O(1) look-up.
     *
     * @return Collection<int, UserCourseProgress>
     */
    public function getUserProgress(User $user): Collection
    {
        $courseIds = $this->courses()->pluck('id');

        return UserCourseProgress::where('user_id', $user->id)
            ->whereIn('path_course_id', $courseIds)
            ->get()
            ->keyBy('path_course_id');
    }
}
