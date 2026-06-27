<?php

namespace App\Enums;

enum RetentionRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case CompletedWithFailures = 'completed_with_failures';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::Running => 'قيد التشغيل',
            self::Completed => 'مكتمل',
            self::CompletedWithFailures => 'مكتمل مع إخفاقات',
            self::Failed => 'فشل',
            self::Cancelled => 'ملغى',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::CompletedWithFailures,
            self::Failed,
            self::Cancelled,
        ], true);
    }
}
