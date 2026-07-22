<?php

namespace App\Enums;

enum ProgramDeliveryMode: string
{
    case Remote = 'remote';
    case InPerson = 'in_person';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Remote => 'عن بُعد',
            self::InPerson => 'حضوري',
            self::Hybrid => 'هايبرد (حضوري وعن بعد)',
        };
    }

    /**
     * Physical venue is collected/shown for in-person and hybrid programs.
     */
    public function hasPhysicalComponent(): bool
    {
        return $this === self::InPerson || $this === self::Hybrid;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
