<?php

namespace App\Models;

use App\Enums\CertificatePdfStatus;
use App\Support\PublicDiskPath;
use Illuminate\Database\Eloquent\Builder;
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
        'certificate_template_id',
        'template_version',
        'data_snapshot',
        'certificate_number',
        'verification_code',
        'file_path',
        'pdf_status',
        'pdf_error',
        'issued_at',
        'emailed_at',
        'override_reason',
        'overridden_by',
        'revoked_at',
        'revoke_reason',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'emailed_at' => 'datetime',
            'revoked_at' => 'datetime',
            'template_version' => 'integer',
            'data_snapshot' => 'array',
            'pdf_status' => CertificatePdfStatus::class,
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * @param  Builder<Certificate>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }

    public function certificateTemplate(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class);
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
        if ($this->file_path === null || $this->isRevoked()) {
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
        if ($this->file_path === null || $this->isRevoked()) {
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
