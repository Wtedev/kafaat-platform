<?php

namespace App\Enums;

enum ProgressStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'لم يبدأ',
            self::InProgress => 'قيد التقدم',
            self::Completed => 'مكتمل',
        };
    }
}
