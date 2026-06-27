<?php

namespace App\Enums;

enum PrivacyPolicyVersionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Active => 'فعّال',
            self::Archived => 'مؤرشف',
        };
    }
}
