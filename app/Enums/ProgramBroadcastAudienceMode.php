<?php

namespace App\Enums;

enum ProgramBroadcastAudienceMode: string
{
    case All = 'all';
    case Statuses = 'statuses';

    public function label(): string
    {
        return match ($this) {
            self::All => 'جميع المسجّلين',
            self::Statuses => 'حسب حالة التسجيل',
        };
    }
}
