<?php

namespace App\Services\Portal;

use App\Enums\MembershipType;
use App\Enums\RegistrationStatus;
use App\Models\Certificate;
use App\Models\LearningPath;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;

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
        ]);

        $completedPaths = $user->learningPathRegistrations()
            ->where('status', RegistrationStatus::Completed)
            ->with('learningPath')
            ->latest('updated_at')
            ->get();

        $completedPrograms = $user->programRegistrations()
            ->where('status', RegistrationStatus::Completed)
            ->with('trainingProgram')
            ->latest('updated_at')
            ->get();

        $completedVolunteering = $user->volunteerRegistrations()
            ->where('status', RegistrationStatus::Completed)
            ->with('opportunity')
            ->latest('updated_at')
            ->get();

        $platformCertificates = $user->certificates()
            ->with('certificateable')
            ->latest('issued_at')
            ->get();

        $approvedVolunteerHours = $user->totalApprovedVolunteerHours();

        /** @var MembershipType $membership */
        $membership = BeneficiaryMembershipResolver::resolve($user);

        $profile = $user->profile;
        $competencyCards = $profile?->presentCompetencyCards() ?? [];
        $recommendations = $user->profileRecommendations;

        return [
            'user' => $user,
            'profile' => $profile,
            'membership' => $membership,
            'completedPaths' => $completedPaths,
            'completedPrograms' => $completedPrograms,
            'completedVolunteering' => $completedVolunteering,
            'platformCertificates' => $platformCertificates,
            'approvedVolunteerHours' => $approvedVolunteerHours,
            'competencyCards' => $competencyCards,
            'recommendations' => $recommendations,
        ];
    }

    public static function certificateTitle(Certificate $certificate): string
    {
        $m = $certificate->certificateable;
        if ($m instanceof LearningPath || $m instanceof TrainingProgram || $m instanceof VolunteerOpportunity) {
            return $m->title;
        }

        return 'شهادة صادرة من المنصة';
    }
}
