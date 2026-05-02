<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\LearningPath;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Support\FilamentAssignmentVisibility;
use App\Support\TrainingEntityAuthorization;
use Illuminate\Auth\Access\HandlesAuthorization;

class CertificatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('certificates.view')
            && $user->hasPermissionTo('roles.view');
    }

    public function view(User $user, Certificate $certificate): bool
    {
        if ((int) $user->id === (int) $certificate->user_id) {
            return true;
        }

        if ($this->staffMayAccessCertificateForEntity($user, $certificate)) {
            return true;
        }

        return $user->hasPermissionTo('certificates.view');
    }

    public function download(User $user, Certificate $certificate): bool
    {
        if ((int) $user->id === (int) $certificate->user_id) {
            return true;
        }

        if ($this->staffMayAccessCertificateForEntity($user, $certificate)) {
            return true;
        }

        return $user->hasPermissionTo('certificates.download');
    }

    public function issue(User $user): bool
    {
        return $user->hasPermissionTo('certificates.issue');
    }

    /**
     * مسؤولو النظام (admin bypass) + فريق العمل المرتبط بالبرنامج/المسار/الفرصة التطوعية
     * (مالك، منشئ، محرر، منسق برنامج مع assigned_to، مسؤول تطوع مع assigned_to، إلخ).
     */
    private function staffMayAccessCertificateForEntity(User $user, Certificate $certificate): bool
    {
        if (! TrainingEntityAuthorization::isActive($user)) {
            return false;
        }

        if (TrainingEntityAuthorization::adminBypass($user)) {
            return true;
        }

        $certificate->loadMissing('certificateable');
        $entity = $certificate->certificateable;

        if ($entity instanceof TrainingProgram) {
            return FilamentAssignmentVisibility::userManagesTrainingProgram($user, $entity);
        }

        if ($entity instanceof LearningPath) {
            return TrainingEntityAuthorization::canViewOperationalPath($user, $entity);
        }

        if ($entity instanceof VolunteerOpportunity) {
            return FilamentAssignmentVisibility::userManagesVolunteerOpportunity($user, $entity);
        }

        return false;
    }
}
