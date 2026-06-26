<?php

namespace App\Enums;

enum LearningPathKind: string
{
    case TrainingPath = 'training_path';
    case Bootcamp = 'bootcamp';

    public function label(): string
    {
        return match ($this) {
            self::TrainingPath => 'مسار تدريبي',
            self::Bootcamp => 'معسكر تدريبي',
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
