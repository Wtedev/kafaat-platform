<?php

namespace App\Enums;

enum ProgramBroadcastRecipientStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد الإرسال',
            self::Sent => 'أُرسل',
            self::Failed => 'فشل',
            self::Skipped => 'تخطّي',
        };
    }
}
