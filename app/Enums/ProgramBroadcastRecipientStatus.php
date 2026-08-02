<?php

namespace App\Enums;

enum ProgramBroadcastRecipientStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد الإرسال',
            self::Processing => 'جارٍ الإرسال',
            self::Sent => 'أُرسل',
            self::Failed => 'فشل',
            self::Skipped => 'تخطّي',
        };
    }

    /**
     * Statuses that still need work before the broadcast can finish.
     *
     * @return list<self>
     */
    public static function incompleteCases(): array
    {
        return [
            self::Pending,
            self::Processing,
        ];
    }

    /**
     * @return list<string>
     */
    public static function incompleteValues(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::incompleteCases(),
        );
    }
}
