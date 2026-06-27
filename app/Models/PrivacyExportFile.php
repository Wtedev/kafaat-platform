<?php

namespace App\Models;

use App\Enums\PrivacyExportFileStatus;
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
        'first_downloaded_at',
        'last_downloaded_at',
        'download_count',
        'status',
        'failure_code',
    ];

    protected $hidden = [
        'disk',
        'path',
        'sha256_checksum',
    ];

    protected function casts(): array
    {
        return [
            'status' => PrivacyExportFileStatus::class,
            'generated_at' => 'datetime',
            'expires_at' => 'datetime',
            'first_downloaded_at' => 'datetime',
            'last_downloaded_at' => 'datetime',
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

    public function isDownloadable(): bool
    {
        return $this->status === PrivacyExportFileStatus::Ready
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
