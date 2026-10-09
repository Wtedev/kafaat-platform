<?php

namespace App\Enums;

enum IdentityCategory: string
{
    case Saudi = 'saudi';
    case Resident = 'resident';

    public function label(): string
    {
        return match ($this) {
            self::Saudi => 'سعودي',
            self::Resident => 'مقيم',
        };
    }

    /**
     * Document-type label shown next to the identity number field.
     */
    public function documentLabel(): string
    {
        return match ($this) {
            self::Saudi => 'هوية وطنية',
            self::Resident => 'إقامة',
        };
    }

    public function toIdentityType(): IdentityType
    {
        return match ($this) {
            self::Saudi => IdentityType::NationalId,
            self::Resident => IdentityType::Iqama,
        };
    }

    public static function fromIdentityType(IdentityType $type): self
    {
        return match ($type) {
            IdentityType::NationalId => self::Saudi,
            IdentityType::Iqama => self::Resident,
        };
    }
}
