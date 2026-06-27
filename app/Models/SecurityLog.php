<?php

namespace App\Models;

use App\Enums\SecurityLogResult;
use App\Enums\SecurityLogSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event',
        'result',
        'severity',
        'request_id',
        'ip_address',
        'user_agent',
        'identifier_hash',
        'metadata',
        'occurred_at',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'result' => SecurityLogResult::class,
            'severity' => SecurityLogSeverity::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): bool => false);
        static::deleting(fn (): bool => false);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
