<?php

namespace App\Enums;

enum RetentionRunMode: string
{
    case Preview = 'preview';
    case Execute = 'execute';

    public function label(): string
    {
        return match ($this) {
            self::Preview => 'معاينة',
            self::Execute => 'تنفيذ',
        };
    }
}
