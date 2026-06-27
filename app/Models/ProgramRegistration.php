<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Services\Inbox\InboxNotificationService;
use App\Services\ProgramAttendanceService;
use App\Support\FilamentAssignmentVisibility;
use App\Support\RegistrationEligibilitySupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramRegistration extends Model
{
    protected $fillable = [
        'training_program_id',
        'user_id',
        'status',
        'approved_by',
        'approved_at',
        'rejected_reason',
        'attendance_percentage',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'approved_at' => 'datetime',
            'attendance_percentage' => 'decimal:2',
            'score' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $registration): void {
            app(InboxNotificationService::class)->notifyStaffOfNewProgramRegistration($registration);
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
        FilamentAssignmentVisibility::constrainProgramRegistrations($query, $viewer);
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
     * A registration is eligible for a certificate when approved or completed,
     * with attendance and score averaging at least 75%.
     */
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

    public function effectiveAttendancePercentage(): ?float
    {
        $calculated = app(ProgramAttendanceService::class)->calculatePercentage($this);

        if ($calculated !== null) {
            return $calculated;
        }

        if ($this->attendance_percentage === null) {
            return null;
        }

        return (float) $this->attendance_percentage;
    }

    public function certificateForEntity(): ?Certificate
    {
        return Certificate::query()
            ->where('user_id', $this->user_id)
            ->where('certificateable_type', TrainingProgram::class)
            ->where('certificateable_id', $this->training_program_id)
            ->first();
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function trainingProgram(): BelongsTo
    {
        return $this->belongsTo(TrainingProgram::class);
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
        return $this->hasMany(ProgramAttendance::class, 'program_registration_id');
    }

    // ─── Attendance helpers ───────────────────────────────────────────────────

    public function calculateAttendancePercentage(): ?float
    {
        return $this->effectiveAttendancePercentage();
    }
}
