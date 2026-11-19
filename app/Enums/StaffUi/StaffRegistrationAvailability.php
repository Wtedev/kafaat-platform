<?php

namespace App\Enums\StaffUi;

/**
 * Staff-UI registration-window availability for a training program.
 *
 * Ordered evaluation (first match wins):
 * manual_closed (reserved) → not_started → closed → full → open
 */
enum StaffRegistrationAvailability: string
{
    case ManualClosed = 'manual_closed';
    case NotStarted = 'not_started';
    case Closed = 'closed';
    case Full = 'full';
    case Open = 'open';

    public function label(): string
    {
        return match ($this) {
            self::ManualClosed => 'أُغلق يدوياً',
            self::NotStarted => 'لم يبدأ',
            self::Closed => 'مغلق',
            self::Full => 'مكتمل العدد',
            self::Open => 'مفتوح',
        };
    }

    /**
     * Status tone for <x-staff-ui.status>.
     */
    public function tone(): string
    {
        return match ($this) {
            self::ManualClosed => 'danger',
            self::NotStarted => 'warning',
            self::Closed => 'muted',
            self::Full => 'danger',
            self::Open => 'success',
        };
    }
}
