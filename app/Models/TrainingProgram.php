<?php

namespace App\Models;

use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TrainingProgram extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'capacity',
        'start_date',
        'end_date',
        'registration_start',
        'registration_end',
        'status',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status'             => ProgramStatus::class,
            'published_at'       => 'datetime',
            'start_date'         => 'date',
            'end_date'           => 'date',
            'registration_start' => 'date',
            'registration_end'   => 'date',
            'capacity'           => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $program) {
            if (empty($program->slug)) {
                $program->slug = Str::slug($program->title);
            }
        });
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePublished(Builder $query): void
    {
        $query->where('status', ProgramStatus::Published);
    }

    public function scopeDraft(Builder $query): void
    {
        $query->where('status', ProgramStatus::Draft);
    }

    public function scopeArchived(Builder $query): void
    {
        $query->where('status', ProgramStatus::Archived);
    }

    public function scopeRegistrationOpen(Builder $query): void
    {
        $today = Carbon::today();

        $query->where(function (Builder $q) use ($today) {
            $q->whereNull('registration_start')
              ->orWhere('registration_start', '<=', $today);
        })->where(function (Builder $q) use ($today) {
            $q->whereNull('registration_end')
              ->orWhere('registration_end', '>=', $today);
        });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isRegistrationOpen(): bool
    {
        $today = Carbon::today();

        $afterStart = $this->registration_start === null
            || $this->registration_start->lte($today);

        $beforeEnd = $this->registration_end === null
            || $this->registration_end->gte($today);

        return $afterStart && $beforeEnd;
    }

    public function approvedRegistrationsCount(): int
    {
        return $this->registrations()
            ->where('status', RegistrationStatus::Approved->value)
            ->count();
    }

    public function hasCapacity(): bool
    {
        if ($this->capacity === null) {
            return true;
        }

        return $this->approvedRegistrationsCount() < $this->capacity;
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function registrations(): HasMany
    {
        return $this->hasMany(ProgramRegistration::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function certificates(): MorphMany
    {
        return $this->morphMany(Certificate::class, 'certificateable');
    }
}
