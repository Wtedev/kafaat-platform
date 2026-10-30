<?php

namespace App\Support;

use App\Models\User;

final class CertificateAuthorization
{
    public static function canIssue(?User $user): bool
    {
        if ($user === null || ! $user->is_active) {
            return false;
        }

        return $user->hasPermissionTo('certificates.issue')
            || $user->hasPermissionTo('issue_certificates');
    }
}
