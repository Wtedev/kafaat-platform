<?php

namespace App\Enums;

enum PrivacyPolicyAcknowledgementSource: string
{
    case Registration = 'registration';
    case ProfileCompletion = 'profile_completion';
    case PolicyUpdate = 'policy_update';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Registration => 'التسجيل',
            self::ProfileCompletion => 'استكمال الملف',
            self::PolicyUpdate => 'تحديث السياسة',
            self::Manual => 'يدوي',
        };
    }
}
