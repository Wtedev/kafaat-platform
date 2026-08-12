<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

class AttendanceLiveSession extends Model
{
    protected $fillable = [
        'attendable_type',
        'attendable_id',
        'program_prep_day_id',
        'session_date',
        'created_by',
        'opened_by_checker_id',
        'started_at',
        'expires_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function attendable(): MorphTo
    {
        return $this->morphTo();
    }

    public function programPrepDay(): BelongsTo
    {
        return $this->belongsTo(ProgramPrepDay::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function openedByChecker(): BelongsTo
    {
        return $this->belongsTo(ProgramAttendanceChecker::class, 'opened_by_checker_id');
    }

    public function isActive(): bool
    {
        if ($this->expires_at === null || $this->started_at === null) {
            return false;
        }

        if ($this->closed_at !== null) {
            return false;
        }

        // Align with activeSessionFor(): expires_at > now(), and session has started.
        return now()->greaterThanOrEqualTo($this->started_at)
            && now()->lessThan($this->expires_at);
    }

    public function remainingSeconds(): int
    {
        if (! $this->isActive()) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }

    public function effectiveEndsAt(): Carbon
    {
        if ($this->closed_at !== null) {
            return $this->closed_at->copy();
        }

        return $this->expires_at->copy();
    }
}
