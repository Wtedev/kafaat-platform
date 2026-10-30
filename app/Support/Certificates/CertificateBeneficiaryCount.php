<?php

namespace App\Support\Certificates;

final class CertificateBeneficiaryCount
{
    public static function buttonLabel(int $count): string
    {
        return 'إصدار الشهادات لـ ('.$count.') '.self::noun($count);
    }

    public static function noun(int $count): string
    {
        if ($count === 2) {
            return 'مستفيدَين';
        }

        if ($count === 0 || ($count >= 3 && $count <= 10)) {
            return 'مستفيدين';
        }

        return 'مستفيد';
    }
}
