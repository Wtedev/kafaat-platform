<?php

namespace App\Enums;

enum TrainingProgramKind: string
{
    case Course = 'course';
    case Session = 'session';
    case Workshop = 'workshop';
    case Event = 'event';

    public function label(): string
    {
        return match ($this) {
            self::Course => 'دورة تدريبية',
            self::Session => 'لقاء',
            self::Workshop => 'ورشة عمل',
            self::Event => 'فعالية',
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
