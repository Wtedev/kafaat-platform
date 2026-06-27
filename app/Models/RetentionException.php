<?php

namespace App\Models;

use App\Enums\RetentionExceptionReasonCode;
use App\Enums\RetentionExceptionScope;
use App\Enums\RetentionExceptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RetentionException extends Model
{
    protected $fillable = [
        'uuid',
        'resource_type',
        'resource_id',
        'user_id',
        'scope',
        'reason_code',
        'reason',
        'starts_at',
        'ends_at',
        'review_at',
        'status',
        'approved_by',
        'revoked_by',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'reason_code' => RetentionExceptionReasonCode::class,
            'scope' => RetentionExceptionScope::class,
            'status' => RetentionExceptionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'review_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (RetentionException $exception): void {
            if (blank($exception->uuid)) {
                $exception->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isActiveAt(?\DateTimeInterface $at = null): bool
    {
        $at ??= now();

        if ($this->status === RetentionExceptionStatus::Revoked) {
            return false;
        }

        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->starts_at->isAfter($at)) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isBefore($at)) {
            return false;
        }

        if ($this->status === RetentionExceptionStatus::Expired) {
            return false;
        }

        return true;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
