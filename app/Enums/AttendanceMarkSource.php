<?php

namespace App\Enums;

enum AttendanceMarkSource: string
{
    case Self = 'self';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Self => 'ذاتي',
            self::Manual => 'يدوي',
        };
    }
}
