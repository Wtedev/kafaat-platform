<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Certificate extends Model
{
    protected $fillable = [
        'user_id',
        'certificateable_type',
        'certificateable_id',
        'certificate_number',
        'verification_code',
        'file_path',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function certificateable(): MorphTo
    {
        return $this->morphTo();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Return a public URL to the stored PDF, or null if not yet generated.
     */
    public function fileUrl(): ?string
    {
        if ($this->file_path === null) {
            return null;
        }

        return Storage::url($this->file_path);
    }

    /**
     * Return the absolute server path to the PDF file.
     */
    public function absolutePath(): ?string
    {
        if ($this->file_path === null) {
            return null;
        }

        return Storage::path($this->file_path);
    }
}
