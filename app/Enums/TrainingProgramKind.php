<?php

namespace App\Enums;

enum TrainingProgramKind: string
{
    case Course = 'course';
    case Session = 'session';
    case Workshop = 'workshop';
    case Bootcamp = 'bootcamp';
    case Event = 'event';

    public function label(): string
    {
        return match ($this) {
            self::Course => 'دورة',
            self::Session => 'لقاء',
            self::Workshop => 'ورشة عمل',
            self::Bootcamp => 'معسكر تدريبي',
            self::Event => 'فعالية',
        };
    }
}
