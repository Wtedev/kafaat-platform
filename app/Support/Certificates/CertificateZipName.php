<?php

namespace App\Support\Certificates;

class CertificateZipName
{
    public static function make(string $beneficiary, string $certificateNumber): string
    {
        $name = self::clean($beneficiary);
        if ($name === '') {
            $name = 'مستفيد';
        }

        $number = self::clean($certificateNumber);
        if ($number === '') {
            $number = 'certificate';
        }

        return $name.'-'.$number.'.pdf';
    }

    private static function clean(string $value): string
    {
        $value = preg_replace('/[\\\\\\/:*?"<>|\\x00-\\x1F]/u', '', $value) ?? '';
        $value = trim($value, " \t\n\r\0\x0B.");

        return $value;
    }
}
