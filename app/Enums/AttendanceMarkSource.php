<?php

namespace App\Enums;

enum AttendanceMarkSource: string
{
    case Self = 'self';
    case Manual = 'manual';
    case PublicLink = 'public_link';

    public function label(): string
    {
        return match ($this) {
            self::Self => 'ذاتي',
            self::Manual => 'يدوي',
            self::PublicLink => 'رابط عام',
        };
    }
}
