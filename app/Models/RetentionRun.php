<?php

namespace App\Models;

use App\Enums\RetentionRunMode;
use App\Enums\RetentionRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RetentionRun extends Model
{
    protected $fillable = [
        'uuid',
        'retention_policy_id',
        'resource_type',
        'mode',
        'status',
        'started_by',
        'started_at',
        'completed_at',
        'cutoff_at',
        'eligible_count',
        'excluded_count',
        'processed_count',
        'succeeded_count',
        'skipped_count',
        'failed_count',
        'summary',
        'request_id',
    ];

    protected function casts(): array
    {
        return [
            'mode' => RetentionRunMode::class,
            'status' => RetentionRunStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cutoff_at' => 'datetime',
            'eligible_count' => 'integer',
            'excluded_count' => 'integer',
            'processed_count' => 'integer',
            'succeeded_count' => 'integer',
            'skipped_count' => 'integer',
            'failed_count' => 'integer',
            'summary' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (RetentionRun $run): void {
            if (blank($run->uuid)) {
                $run->uuid = (string) Str::uuid();
            }
        });
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(RetentionPolicy::class, 'retention_policy_id');
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RetentionRunItem::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
