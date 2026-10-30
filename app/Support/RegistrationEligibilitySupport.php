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

    /**
     * Beneficiary-facing certificate status and the reason behind it.
     *
     * @return array{status: string, reason: string}
     */
    public static function beneficiaryCertificateCopy(
        ?float $attendancePercentage,
        ?float $score,
        bool $accepted,
        bool $issued,
    ): array {
        if ($issued) {
            return [
                'status' => 'صادرة',
                'reason' => 'صدرت الشهادة ويمكن تنزيلها.',
            ];
        }

        if (! $accepted) {
            return [
                'status' => 'بعد القبول',
                'reason' => 'يُحدد الاستحقاق بعد قبول التسجيل.',
            ];
        }

        $average = self::averageScore($attendancePercentage, $score);
        $minimum = self::plainAmount(self::CERTIFICATE_MIN_AVERAGE);

        if ($average === null) {
            return [
                'status' => 'بانتظار البيانات',
                'reason' => 'يلزم تسجيل الحضور والدرجة، والحد هو متوسط '.$minimum.' من 100.',
            ];
        }

        $shown = self::plainAmount($average);

        if ($average >= self::CERTIFICATE_MIN_AVERAGE) {
            return [
                'status' => 'مؤهل',
                'reason' => 'متوسط الحضور والدرجة '.$shown.' يبلغ الحد المطلوب، والشهادة لم تُصدر بعد.',
            ];
        }

        return [
            'status' => 'غير مؤهل',
            'reason' => 'متوسط الحضور والدرجة '.$shown.' أقل من '.$minimum.'.',
        ];
    }

    public static function plainAmount(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
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
