<?php

namespace App\Enums;

enum SurveyQuestionType: string
{
    case Scale = 'scale';
    case Single = 'single';
    case Multiple = 'multiple';
    case Text = 'text';

    public function label(): string
    {
        return match ($this) {
            self::Scale => 'مقياس من 1 إلى 5',
            self::Single => 'اختيار واحد',
            self::Multiple => 'اختيار متعدد',
            self::Text => 'نص',
        };
    }
}
