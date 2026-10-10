<?php

namespace App\Enums;

enum SurveyType: string
{
    case Pre = 'pre';
    case Post = 'post';
    case Satisfaction = 'satisfaction';

    public function label(): string
    {
        return match ($this) {
            self::Pre => 'قبلي',
            self::Post => 'بعدي',
            self::Satisfaction => 'رضا',
        };
    }
}
