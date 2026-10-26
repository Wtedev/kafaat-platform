<?php

namespace App\Policies;

use App\Models\CertificateTemplate;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Support\CertificateAuthorization;
use Illuminate\Auth\Access\HandlesAuthorization;

class CertificateTemplatePolicy
{
    use HandlesAuthorization;

    public function view(User $user, CertificateTemplate $template): bool
    {
        $template->loadMissing('program');

        return $template->program instanceof TrainingProgram
            && $this->design($user, $template->program);
    }

    public function update(User $user, CertificateTemplate $template): bool
    {
        return $this->view($user, $template);
    }

    public function design(User $user, TrainingProgram $program): bool
    {
        return CertificateAuthorization::canIssue($user);
    }
}
