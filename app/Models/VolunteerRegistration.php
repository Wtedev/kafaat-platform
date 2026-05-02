<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Enums\VolunteerHoursStatus;
use App\Services\Inbox\InboxNotificationService;
use App\Support\FilamentAssignmentVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerRegistration extends Model
{
    protected $fillable = [
        'opportunity_id',
        'user_id',
        'status',
        'approved_by',
        'approved_at',
        'rejected_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $registration): void {
            app(InboxNotificationService::class)->notifyStaffOfNewVolunteerRegistration($registration);
        });
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePending(Builder $query): void
    {
        $query->where('status', RegistrationStatus::Pending);
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where('status', RegistrationStatus::Approved);
    }

    public function scopeRejected(Builder $query): void
    {
        $query->where('status', RegistrationStatus::Rejected);
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', RegistrationStatus::Completed);
    }

    public function scopeForFilamentAssignmentAccess(Builder $query, ?User $viewer): void
    {
        FilamentAssignmentVisibility::constrainVolunteerRegistrations($query, $viewer);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isApproved(): bool
    {
        return $this->status === RegistrationStatus::Approved;
    }

    public function isCompleted(): bool
    {
        return $this->status === RegistrationStatus::Completed;
    }

    /**
     * Sum of approved volunteer hours logged by this user for this opportunity.
     */
    public function getApprovedHours(): float
    {
        return (float) VolunteerHour::query()
            ->where('user_id', $this->user_id)
            ->where('opportunity_id', $this->opportunity_id)
            ->where('status', VolunteerHoursStatus::Approved->value)
            ->sum('hours');
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(VolunteerOpportunity::class, 'opportunity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
