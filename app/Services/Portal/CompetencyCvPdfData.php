<?php

namespace App\Services\Portal;

use App\Models\Certificate;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Merges platform activity into CV-shaped rows for PDF / premium layout.
 */
final class CompetencyCvPdfData
{
    /**
     * The user's own experience rows, marked as theirs.
     *
     * @return list<array<string, mixed>>
     */
    public static function mergedExperience(User $user, array $userExperience, string $locale): array
    {
        return array_map(fn (array $row): array => array_merge($row, ['source' => 'user']), array_values($userExperience));
    }

    /**
     * External courses + program certificates (when a certificate file exists).
     *
     * @param  Collection<int, Certificate>  $platformCertificates
     * @return list<array<string, mixed>>
     */
    public static function mergedCourses(array $externalCourses, Collection $platformCertificates, string $locale): array
    {
        $out = [];
        foreach ($externalCourses as $row) {
            $out[] = array_merge($row, ['source' => 'user']);
        }
        $label = CvUiTranslator::t($locale, 'program_certificate');
        foreach ($platformCertificates as $cert) {
            $m = $cert->certificateable;
            if (! $m instanceof TrainingProgram) {
                continue;
            }
            if (! $cert->file_path) {
                continue;
            }
            $out[] = [
                'title' => $m->title,
                'provider' => CvUiTranslator::t($locale, 'kafaat'),
                'date' => $cert->issued_at?->format('Y-m-d'),
                'certificate_url' => route('certificates.verify', $cert->verification_code, absolute: true),
                'description' => $label,
                'source' => 'platform_program',
            ];
        }

        return $out;
    }
}
