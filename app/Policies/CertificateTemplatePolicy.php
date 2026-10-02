<?php

namespace App\Policies;

use App\Models\CertificateTemplate;
use App\Models\LearningPath;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Support\TrainingEntityAuthorization;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

class CertificateTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return TrainingEntityAuthorization::adminBypass($user)
            || $user->checkPermissionTo('certificate_templates.manage');
    }

    public function view(User $user, CertificateTemplate $template): bool
    {
        return $this->manage($user, $template);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, CertificateTemplate $template): bool
    {
        return $this->manage($user, $template);
    }

    public function delete(User $user, CertificateTemplate $template): bool
    {
        return $this->manage($user, $template);
    }

    /**
     * المدير، أو الموظف الذي يملك تعديل البرنامج (programs.update مع دور مالك/منشئ/محرر).
     */
    public function manage(User $user, CertificateTemplate $template): bool
    {
        return $this->canManageOwner($user, $template->owner);
    }

    public function manageForProgram(User $user, TrainingProgram $program): bool
    {
        return $this->canManageOwner($user, $program);
    }

    public function manageForOwner(User $user, Model $owner): bool
    {
        return $this->canManageOwner($user, $owner);
    }

    private function canManageOwner(User $user, ?Model $owner): bool
    {
        if (TrainingEntityAuthorization::adminBypass($user)) {
            return true;
        }

        if ($owner instanceof TrainingProgram) {
            return TrainingEntityAuthorization::canUpdateProgram($user, $owner);
        }

        if ($owner instanceof LearningPath) {
            return TrainingEntityAuthorization::canUpdatePath($user, $owner);
        }

        if ($owner instanceof VolunteerOpportunity) {
            return $user->can('update', $owner);
        }

        return false;
    }
}
