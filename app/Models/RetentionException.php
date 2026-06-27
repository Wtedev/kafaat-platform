<?php

namespace App\Models;

use App\Enums\RetentionExceptionReasonCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetentionException extends Model
{
    protected $fillable = [
        'uuid',
        'resource_type',
        'resource_id',
        'user_id',
        'reason_code',
        'reason',
        'starts_at',
        'ends_at',
        'approved_by',
        'revoked_by',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'reason_code' => RetentionExceptionReasonCode::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isActiveAt(?\DateTimeInterface $at = null): bool
    {
        $at ??= now();

        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->starts_at->isAfter($at)) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isBefore($at)) {
            return false;
        }

        return true;
    }
}
