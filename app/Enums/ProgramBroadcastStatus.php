<?php

namespace App\Enums;

enum ProgramBroadcastStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Sending = 'sending';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Queued => 'في الانتظار',
            self::Sending => 'جارٍ الإرسال',
            self::Completed => 'مكتمل',
            self::CompletedWithErrors => 'مكتمل مع أخطاء',
            self::Failed => 'فشل',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Queued => 'info',
            self::Sending => 'warning',
            self::Completed => 'success',
            self::CompletedWithErrors => 'warning',
            self::Failed => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::CompletedWithErrors,
            self::Failed,
        ], true);
    }

    public function isMutableContent(): bool
    {
        return $this === self::Draft;
    }

    public function hasStartedSending(): bool
    {
        return $this !== self::Draft;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
