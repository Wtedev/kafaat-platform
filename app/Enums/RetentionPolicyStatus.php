<?php

namespace App\Enums;

enum RetentionPolicyStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Inactive = 'inactive';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Active => 'فعّالة',
            self::Inactive => 'معطّلة',
            self::Superseded => 'مستبدلة',
        };
    }

    public function isExecutable(): bool
    {
        return $this === self::Active;
    }
}
