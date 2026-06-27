<?php

namespace App\Data\Privacy\Export;

use App\Models\User;
use App\Services\Documents\CvDocumentService;

final readonly class PersonalDataExportBundle
{
    /**
     * @param  array<string, mixed>  $sections
     */
    public function __construct(
        public array $sections,
        public ?string $cvSourcePath,
        public ?string $cvArchiveName,
    ) {}

    public static function build(User $user, CvDocumentService $cvService): self
    {
        $user->loadMissing(['profile']);

        $cv = $cvService->currentCv($user);
        $cvPath = null;
        if ($cv !== null && filled($cv->disk) && filled($cv->path)) {
            $cvPath = $cv->path;
        }

        $sections = [
            'account' => AccountExportData::forUser($user),
            'profile' => ProfileExportData::forUser($user),
            'privacy_policy_acknowledgements' => PolicyAcknowledgementExportData::forUser($user),
            'candidate_pool_consent_history' => CandidateConsentExportData::forUser($user),
            'program_registrations' => ProgramRegistrationExportData::forUser($user),
            'path_registrations' => PathRegistrationExportData::forUser($user),
            'volunteer_registrations' => VolunteerRegistrationExportData::forUser($user),
            'attendance' => AttendanceExportData::forUser($user),
            'certificates' => CertificateExportData::forUser($user),
            'activity_history' => ActivityExportData::forUser($user),
            'documents_manifest' => DocumentManifestExportData::forUser($user, $cvService),
        ];

        return new self(
            sections: array_filter($sections, fn (mixed $value): bool => $value !== null && $value !== []),
            cvSourcePath: $cvPath,
            cvArchiveName: $cvPath !== null ? 'documents/cv.pdf' : null,
        );
    }
}
