<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivacyExportFile extends Model
{
    protected $fillable = [
        'uuid',
        'privacy_request_id',
        'user_id',
        'disk',
        'path',
        'format',
        'size_bytes',
        'sha256_checksum',
        'generated_at',
        'expires_at',
        'downloaded_at',
        'download_count',
        'status',
    ];

    protected $hidden = [
        'disk',
        'path',
        'sha256_checksum',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'expires_at' => 'datetime',
            'downloaded_at' => 'datetime',
            'size_bytes' => 'integer',
            'download_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function privacyRequest(): BelongsTo
    {
        return $this->belongsTo(PrivacyRequest::class);
    }
}
