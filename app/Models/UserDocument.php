<?php

namespace App\Models;

use App\Enums\UserDocumentStatus;
use App\Enums\UserDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDocument extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'document_type',
        'disk',
        'path',
        'mime_type',
        'extension',
        'size_bytes',
        'sha256_checksum',
        'status',
        'uploaded_by',
        'uploaded_at',
        'deleted_at',
    ];

    protected $hidden = [
        'disk',
        'path',
        'sha256_checksum',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => UserDocumentType::class,
            'status' => UserDocumentStatus::class,
            'uploaded_at' => 'datetime',
            'deleted_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === UserDocumentStatus::Active;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
