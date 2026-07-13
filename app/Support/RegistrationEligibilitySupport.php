<?php

namespace App\Support;

class RegistrationEligibilitySupport
{
    public const CERTIFICATE_MIN_AVERAGE = 75.0;

    public static function averageScore(?float $attendancePercentage, ?float $score): ?float
    {
        if ($attendancePercentage === null || $score === null) {
            return null;
        }

        return round(((float) $attendancePercentage + (float) $score) / 2, 2);
    }

    public static function isEligible(?float $attendancePercentage, ?float $score): bool
    {
        $average = self::averageScore($attendancePercentage, $score);

        return $average !== null && $average >= self::CERTIFICATE_MIN_AVERAGE;
    }

    public static function eligibilityLabel(?float $attendancePercentage, ?float $score): string
    {
        if ($attendancePercentage === null || $score === null) {
            return 'بانتظار البيانات';
        }

        return self::isEligible($attendancePercentage, $score) ? 'مؤهل' : 'غير مؤهل حتى الآن';
    }

    public static function eligibilityColor(?float $attendancePercentage, ?float $score): string
    {
        $label = self::eligibilityLabel($attendancePercentage, $score);

        return match ($label) {
            'مؤهل' => 'success',
            'غير مؤهل حتى الآن', 'غير مؤهل' => 'danger',
            default => 'warning',
        };
    }
}
