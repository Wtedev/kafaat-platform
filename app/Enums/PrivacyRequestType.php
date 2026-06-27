<?php

namespace App\Enums;

enum PrivacyRequestType: string
{
    case AccountDeletion = 'account_deletion';
    case DataAccess = 'data_access';
    case DataCorrection = 'data_correction';
    case DataExport = 'data_export';

    public function label(): string
    {
        return match ($this) {
            self::AccountDeletion => 'حذف الحساب والبيانات',
            self::DataAccess => 'الوصول إلى بياناتي',
            self::DataCorrection => 'تصحيح بياناتي',
            self::DataExport => 'تصدير بياناتي',
        };
    }

    public function requiresIdentityVerification(): bool
    {
        return $this === self::AccountDeletion;
    }

    public function usesDeletionPlan(): bool
    {
        return $this === self::AccountDeletion;
    }
}
