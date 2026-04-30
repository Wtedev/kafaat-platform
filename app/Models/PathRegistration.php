<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected function casts(): array
    {
        return [
            'status'       => RegistrationStatus::class,
            'approved_at'  => 'datetime',
            'completed_at' => 'datetime',
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

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isApproved(): bool
    {
        return $this->status === RegistrationStatus::Approved;
    }

    public function isCompleted(): bool
    {
        return $this->status === RegistrationStatus::Completed;
    }

    public function canAccessCourses(): bool
    {
        return in_array($this->status, [
            RegistrationStatus::Approved,
            RegistrationStatus::Completed,
        ], true);
    }
}
