<?php

namespace App\Enums;

enum SupportMessageSenderType: string
{
    case Beneficiary = 'beneficiary';
    case Support = 'support';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Beneficiary => 'أنت',
            self::Support => 'فريق الدعم',
            self::System => 'النظام',
        };
    }

    public function adminLabel(): string
    {
        return match ($this) {
            self::Beneficiary => 'المستفيد',
            self::Support => 'فريق الدعم',
            self::System => 'النظام',
        };
    }

    public function isSupportSide(): bool
    {
        return $this === self::Support || $this === self::System;
    }
}
