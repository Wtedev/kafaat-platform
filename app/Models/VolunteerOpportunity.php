<?php

namespace App\Models;

use App\Enums\OpportunityStatus;
use App\Enums\RegistrationStatus;
use App\Enums\VolunteerHoursStatus;
use App\Services\Inbox\InboxNotificationService;
use App\Support\FilamentAssignmentVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class VolunteerOpportunity extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'capacity',
        'hours_expected',
        'start_date',
        'end_date',
        'status',
        'published_at',
        'created_by',
        'updated_by',
        'assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'status' => OpportunityStatus::class,
            'published_at' => 'datetime',
            'start_date' => 'date',
            'end_date' => 'date',
            'capacity' => 'integer',
            'hours_expected' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $opportunity) {
            if (empty($opportunity->slug)) {
                $opportunity->slug = Str::slug($opportunity->title);
            }

            if ($opportunity->assigned_to === null && Auth::check()) {
                $user = Auth::user();
                if ($user->hasRole('volunteering_manager') && ! FilamentAssignmentVisibility::bypasses($user)) {
                    $opportunity->assigned_to = $user->id;
                }
            }
        });

        static::updated(function (self $opportunity): void {
            if ($opportunity->status !== OpportunityStatus::Published) {
                return;
            }

            $watched = [
                'title', 'slug', 'description', 'capacity', 'hours_expected',
                'start_date', 'end_date', 'status', 'published_at',
            ];

            if (! $opportunity->wasChanged($watched)) {
                return;
            }

            $editor = Auth::user();
            app(InboxNotificationService::class)->volunteerOpportunityUpdated(
                $opportunity,
                $editor instanceof User ? $editor : null,
            );
        });
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePublished(Builder $query): void
    {
        $query->where('status', OpportunityStatus::Published);
    }

    public function scopeDraft(Builder $query): void
    {
        $query->where('status', OpportunityStatus::Draft);
    }

    public function scopeArchived(Builder $query): void
    {
        $query->where('status', OpportunityStatus::Archived);
    }

    public function scopeForFilamentAssignmentAccess(Builder $query, ?User $viewer): void
    {
        FilamentAssignmentVisibility::constrainVolunteerOpportunities($query, $viewer);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

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

    /**
     * Total approved volunteer hours logged against this opportunity.
     */
    public function totalApprovedHours(): float
    {
        return (float) $this->volunteerHours()
            ->where('status', VolunteerHoursStatus::Approved->value)
            ->sum('hours');
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function registrations(): HasMany
    {
        return $this->hasMany(VolunteerRegistration::class, 'opportunity_id');
    }

    public function volunteerHours(): HasMany
    {
        return $this->hasMany(VolunteerHour::class, 'opportunity_id');
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
