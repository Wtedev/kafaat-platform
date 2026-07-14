<?php

namespace App\Enums;

enum IdentityType: string
{
    case NationalId = 'national_id';
    case Iqama = 'iqama';

    public function label(): string
    {
        return match ($this) {
            self::NationalId => 'هوية وطنية',
            self::Iqama => 'إقامة',
        };
    }
}
