<?php

namespace App\Enums;

enum PrivacyRequestType: string
{
    case AccountDeletion = 'account_deletion';

    public function label(): string
    {
        return match ($this) {
            self::AccountDeletion => 'حذف الحساب والبيانات',
        };
    }

    public function requiresIdentityVerification(): bool
    {
        return true;
    }
}
