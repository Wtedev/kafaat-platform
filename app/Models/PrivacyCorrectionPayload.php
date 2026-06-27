<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivacyCorrectionPayload extends Model
{
    protected $fillable = [
        'privacy_request_id',
        'field_code',
        'encrypted_value',
        'value_lookup_hash',
        'value_last4',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function privacyRequest(): BelongsTo
    {
        return $this->belongsTo(PrivacyRequest::class);
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
