<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Services\Inbox\InboxNotificationService;
use App\Services\PathAttendanceService;
use App\Support\FilamentAssignmentVisibility;
use App\Support\RegistrationEligibilitySupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PathRegistration extends Model
{
    protected $fillable = [
        'learning_path_id',
        'user_id',
        'status',
        'approved_by',
        'approved_at',
        'completed_at',
        'rejected_reason',
        'attendance_percentage',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'attendance_percentage' => 'decimal:2',
            'score' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $registration): void {
            app(InboxNotificationService::class)->notifyStaffOfNewPathRegistration($registration);
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

    /**
     * Filament list: operational learner data only for path stakeholders or own registration.
     */
    public function scopeForFilamentAssignmentAccess(Builder $query, ?User $viewer): void
    {
        FilamentAssignmentVisibility::constrainPathRegistrations($query, $viewer);
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(PathAttendance::class, 'path_registration_id');
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
     * Approved or completed path registration: can view path programs and progress in the portal.
     */
    public function canAccessPathPrograms(): bool
    {
        return in_array($this->status, [
            RegistrationStatus::Approved,
            RegistrationStatus::Completed,
        ], true);
    }

    public function effectiveAttendancePercentage(): ?float
    {
        $calculated = app(PathAttendanceService::class)->calculatePercentage($this);

        if ($calculated !== null) {
            return $calculated;
        }

        if ($this->attendance_percentage === null) {
            return null;
        }

        return (float) $this->attendance_percentage;
    }

    public function isEligibleForCertificate(): bool
    {
        if (! in_array($this->status, [
            RegistrationStatus::Approved,
            RegistrationStatus::Completed,
        ], true)) {
            return false;
        }

        return RegistrationEligibilitySupport::isEligible(
            $this->effectiveAttendancePercentage(),
            $this->score !== null ? (float) $this->score : null,
        );
    }

    public function certificateForEntity(): ?Certificate
    {
        return Certificate::query()
            ->where('user_id', $this->user_id)
            ->where('certificateable_type', LearningPath::class)
            ->where('certificateable_id', $this->learning_path_id)
            ->first();
    }
}
