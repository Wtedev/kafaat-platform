<?php

namespace App\Services\Portal;

use App\Enums\MembershipType;
use App\Enums\RegistrationStatus;
use App\Models\Certificate;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Services\CandidatePool\CandidatePoolConsentService;
use App\Services\CandidatePool\CandidatePoolConsentVersionService;
use App\Support\CompetencyDataCompleteness;

final class CompetencyProfilePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function make(User $user): array
    {
        $user->load([
            'profile',
            'profileRecommendations',
            'candidatePoolPreference',
        ]);

        $completedPrograms = $user->programRegistrations()
            ->where('status', RegistrationStatus::Completed)
            ->whereHas('trainingProgram', fn ($query) => $query->published())
            ->with('trainingProgram')
            ->latest('updated_at')
            ->get();

        $platformCertificates = $user->certificates()
            ->with('certificateable')
            ->latest('issued_at')
            ->get();

        /** @var MembershipType $membership */
        $membership = BeneficiaryMembershipResolver::resolve($user);

        $profile = $user->profile;
        $competencyCards = $profile?->presentCompetencyCards() ?? [];
        $recommendations = $user->profileRecommendations;

        $cvLocale = $profile?->cvUiLocale() ?? 'ar';
        $labels = CvUiTranslator::sectionLabels($cvLocale);
        $userExperience = $profile?->cvExperienceStructured() ?? [];
        $mergedExperience = CompetencyCvPdfData::mergedExperience(
            $user,
            $userExperience,
            $cvLocale,
        );
        $mergedCourses = CompetencyCvPdfData::mergedCourses(
            $profile?->cvExternalCoursesStructured() ?? [],
            $platformCertificates,
            $cvLocale,
        );

        return [
            'user' => $user,
            'profile' => $profile,
            'membership' => $membership,
            'pdfJobTitle' => $profile?->pdfHeadlineForExport($membership, $cvLocale),
            'completedPrograms' => $completedPrograms,
            'platformCertificates' => $platformCertificates,
            'competencyCards' => $competencyCards,
            'recommendations' => $recommendations,
            'cvLocale' => $cvLocale,
            'cvLabels' => $labels,
            'mergedExperience' => $mergedExperience,
            'mergedCourses' => $mergedCourses,
            'employmentConsentGranted' => $user->candidatePoolPreference?->current_status?->value === 'granted',
            'employmentConsentAvailable' => CandidatePoolConsentVersionService::activeVersion() !== null,
            'employmentConsentText' => app(CandidatePoolConsentService::class)->consentText(),
            'competencyCompleteness' => CompetencyDataCompleteness::forUser($user),
        ];
    }

    public static function certificateTitle(Certificate $certificate): string
    {
        $m = $certificate->certificateable;
        if ($m instanceof TrainingProgram || $m instanceof VolunteerOpportunity) {
            return $m->title;
        }

        return 'شهادة صادرة من المنصة';
    }
}
