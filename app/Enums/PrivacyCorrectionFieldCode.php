<?php

namespace App\Enums;

use App\Models\Certificate;
use App\Models\User;

enum PrivacyCorrectionFieldCode: string
{
    case StructuredName = 'structured_name';
    case IdentityNumber = 'identity_number';
    case BirthDate = 'birth_date';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::StructuredName => 'الاسم الرباعي',
            self::IdentityNumber => 'رقم الهوية',
            self::BirthDate => 'تاريخ الميلاد',
            self::Email => 'البريد الإلكتروني',
        };
    }

    public function requiresSensitiveVerification(): bool
    {
        return in_array($this, [self::IdentityNumber, self::Email], true);
    }

    public function isSelfServiceFor(User $user): bool
    {
        return match ($this) {
            self::StructuredName => ! $this->userHasCertificates($user),
            self::IdentityNumber => ! $user->hasIdentityOnRecord(),
            self::BirthDate => ! $this->userHasCertificates($user) && ! $user->hasIdentityOnRecord(),
            self::Email => false,
        };
    }

    public function selfServiceRoute(): ?string
    {
        return match ($this) {
            self::StructuredName, self::IdentityNumber, self::BirthDate => 'portal.profile',
            self::Email => null,
        };
    }

    private function userHasCertificates(User $user): bool
    {
        return Certificate::query()->where('user_id', $user->id)->exists();
    }
}
