<?php

namespace App\Enums;

enum RetentionExceptionStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعّال',
            self::Expired => 'منتهٍ',
            self::Revoked => 'ملغى',
        };
    }
}
