<?php

namespace App\Enums;

enum ProgramDeliveryMode: string
{
    case Remote = 'remote';
    case InPerson = 'in_person';

    public function label(): string
    {
        return match ($this) {
            self::Remote => 'عن بُعد',
            self::InPerson => 'حضوري',
        };
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
