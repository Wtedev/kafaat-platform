<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
            'status'                => RegistrationStatus::class,
            'approved_at'           => 'datetime',
            'attendance_percentage' => 'decimal:2',
            'score'                 => 'decimal:2',
        ];
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
     * A registration is eligible for a certificate when:
     *  - status is completed
     *  - attendance_percentage >= 80
     *  - score, if provided, is >= 60
     */
    public function isEligibleForCertificate(): bool
    {
        if (! $this->isCompleted()) {
            return false;
        }

        if ((float) $this->attendance_percentage < 80.0) {
            return false;
        }

        if ($this->score !== null && (float) $this->score < 60.0) {
            return false;
        }

        return true;
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
}
