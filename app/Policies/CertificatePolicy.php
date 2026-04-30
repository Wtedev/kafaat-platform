<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CertificatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('certificates.view');
    }

    public function view(User $user, Certificate $certificate): bool
    {
        // The certificate owner can always view their own certificate
        if ($user->id === $certificate->user_id) {
            return true;
        }

        return $user->hasPermissionTo('certificates.view');
    }

    public function download(User $user, Certificate $certificate): bool
    {
        // Only the certificate owner may download it
        if ($user->id === $certificate->user_id) {
            return true;
        }

        return $user->hasPermissionTo('certificates.download');
    }

    public function issue(User $user): bool
    {
        return $user->hasPermissionTo('certificates.issue');
    }
}
