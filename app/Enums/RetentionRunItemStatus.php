<?php

namespace App\Enums;

enum RetentionRunItemStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Skipped = 'skipped';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::Running => 'قيد المعالجة',
            self::Succeeded => 'نجح',
            self::Skipped => 'تخطّي',
            self::Failed => 'فشل',
        };
    }
}
