<?php

namespace App\Services\Identity;

class SaudiPhoneService
{
    /**
     * Normalize Saudi mobile numbers to E.164 +9665XXXXXXXX.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', self::convertDigitsToLatin($value)) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '966')) {
            $digits = substr($digits, 3);
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '5')) {
            $digits = '0'.$digits;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '05')) {
            return '+966'.substr($digits, 1);
        }

        return null;
    }

    public static function isValid(?string $value): bool
    {
        $normalized = self::normalize($value);

        if ($normalized === null) {
            return false;
        }

        return (bool) preg_match('/^\+9665\d{8}$/', $normalized);
    }

    public static function convertDigitsToLatin(string $value): string
    {
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($persian, $latin, str_replace($arabic, $latin, $value));
    }
}
