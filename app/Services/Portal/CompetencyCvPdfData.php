<?php

namespace App\Services\Portal;

use App\Models\Certificate;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerRegistration;
use Illuminate\Support\Collection;

/**
 * Merges platform activity into CV-shaped rows for PDF / premium layout.
 */
final class CompetencyCvPdfData
{
    /**
     * User experience rows + completed volunteering as synthetic experience.
     *
     * @param  Collection<int, VolunteerRegistration>  $completedVolunteering
     * @return list<array<string, mixed>>
     */
    public static function mergedExperience(User $user, array $userExperience, Collection $completedVolunteering, string $locale): array
    {
        $out = [];
        foreach ($userExperience as $row) {
            $out[] = array_merge($row, ['source' => 'user']);
        }
        $plat = CvUiTranslator::t($locale, 'kafaat');
        $volLabel = CvUiTranslator::t($locale, 'volunteering');
        foreach ($completedVolunteering as $reg) {
            $title = $reg->opportunity?->title ?? $volLabel;
            $out[] = [
                'title' => $title,
                'organization' => $plat,
                'type' => 'on_site',
                'employment_type' => 'volunteer',
                'start_date' => null,
                'end_date' => $reg->updated_at?->format('Y-m-d'),
                'is_current' => false,
                'description' => $volLabel,
                'source' => 'platform_volunteer',
            ];
        }

        return $out;
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
                'certificate_url' => $cert->fileUrl(),
                'description' => $label,
                'source' => 'platform_program',
            ];
        }

        return $out;
    }
}
