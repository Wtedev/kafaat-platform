<?php

namespace App\Models;

use App\Support\PublicDiskPath;
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

        return PublicDiskPath::url($this->file_path);
    }

    /**
     * Authenticated download URL (streams via CertificateDownloadController).
     * Works on servers where the public/storage symlink is missing.
     */
    public function downloadUrl(): ?string
    {
        if ($this->file_path === null) {
            return null;
        }

        $relative = PublicDiskPath::normalize($this->file_path);
        if ($relative === null || str_starts_with($relative, 'http://') || str_starts_with($relative, 'https://')) {
            return null;
        }

        if (! Storage::disk('public')->exists($relative)) {
            return null;
        }

        return route('certificates.download', ['certificate' => $this->getKey()]);
    }

    /**
     * Return the absolute server path to the PDF file.
     */
    public function absolutePath(): ?string
    {
        if ($this->file_path === null) {
            return null;
        }

        $relative = PublicDiskPath::normalize($this->file_path);
        if ($relative === null || str_starts_with($relative, 'http://') || str_starts_with($relative, 'https://')) {
            return null;
        }

        return Storage::disk('public')->path($relative);
    }
}
