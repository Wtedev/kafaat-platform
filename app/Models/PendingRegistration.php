<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PendingRegistration extends Model
{
    use HasUuids;

    protected $fillable = [
        'email',
        'password_hash',
        'payload',
        'code_hash',
        'attempts',
        'resend_count',
        'expires_at',
        'last_sent_at',
        'intended_url',
        'consumed_at',
    ];

    protected $hidden = [
        'password_hash',
        'code_hash',
        'payload',
    ];

    protected $casts = [
        'payload' => 'encrypted:array',
        'attempts' => 'integer',
        'resend_count' => 'integer',
        'expires_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isActive(): bool
    {
        return ! $this->isConsumed() && ! $this->isExpired();
    }
}
