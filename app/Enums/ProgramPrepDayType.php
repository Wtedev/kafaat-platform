<?php

namespace App\Enums;

enum ProgramPrepDayType: string
{
    case InPerson = 'in_person';
    case Remote = 'remote';

    public function label(): string
    {
        return match ($this) {
            self::InPerson => 'حضوري',
            self::Remote => 'عن بُعد',
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
