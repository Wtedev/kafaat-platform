<?php

namespace App\Data\Privacy\Export;

use App\Models\Certificate;
use App\Models\User;

final readonly class CertificateExportData
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(User $user): array
    {
        return Certificate::query()
            ->where('user_id', $user->id)
            ->with('certificateable')
            ->orderBy('issued_at')
            ->get()
            ->map(function (Certificate $certificate): array {
                $title = null;
                if ($certificate->relationLoaded('certificateable') || $certificate->certificateable !== null) {
                    $title = $certificate->certificateable?->title ?? null;
                }

                return [
                    'certificate_number' => $certificate->certificate_number,
                    'program_or_path_title' => $title,
                    'issued_at' => $certificate->issued_at?->toIso8601String(),
                    'verification_url' => filled($certificate->verification_code)
                        ? route('certificates.verify', ['code' => $certificate->verification_code])
                        : null,
                ];
            })
            ->values()
            ->all();
    }
}
