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
            self::Bootcamp => 'معسكر',
        };
    }
}
