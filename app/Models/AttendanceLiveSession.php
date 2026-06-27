<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AttendanceLiveSession extends Model
{
    protected $fillable = [
        'attendable_type',
        'attendable_id',
        'created_by',
        'started_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function attendable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return now()->between($this->started_at, $this->expires_at);
    }

    public function remainingSeconds(): int
    {
        if (! $this->isActive()) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }
}
