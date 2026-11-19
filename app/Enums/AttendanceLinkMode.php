<?php

namespace App\Enums;

/**
 * How trainees are checked in on an attendance day.
 */
enum AttendanceLinkMode: string
{
    /** A prep officer opens the scan link and scans each trainee's attendance QR at the door. */
    case Qr = 'qr';

    /** Trainees open the link and enter their identity number. */
    case Remote = 'remote';

    public function label(): string
    {
        return match ($this) {
            self::Qr => 'QR حضوري',
            self::Remote => 'رابط عن بُعد',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Qr => 'رابط مسح التحضير لمسؤول التحضير، يمسح به QR كل متدرب عند الدخول.',
            self::Remote => 'رابط يُرسل للمشاركين، يكتبون رقم هويتهم ويُحضَّرون.',
        };
    }
}
