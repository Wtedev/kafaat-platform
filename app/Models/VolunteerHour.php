<?php

namespace App\Models;

use App\Enums\VolunteerHoursStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerHour extends Model
{
    protected $fillable = [
        'user_id',
        'opportunity_id',
        'hours',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => VolunteerHoursStatus::class,
            'approved_at' => 'datetime',
            'hours' => 'decimal:2',
        ];
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePending(Builder $query): void
    {
        $query->where('status', VolunteerHoursStatus::Pending);
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where('status', VolunteerHoursStatus::Approved);
    }

    public function scopeRejected(Builder $query): void
    {
        $query->where('status', VolunteerHoursStatus::Rejected);
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(VolunteerOpportunity::class, 'opportunity_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
