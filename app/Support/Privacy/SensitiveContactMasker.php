<?php

namespace App\Support\Privacy;

final class SensitiveContactMasker
{
    public static function maskEmail(?string $email): ?string
    {
        if ($email === null || $email === '') {
            return null;
        }

        if (! str_contains($email, '@')) {
            return '***';
        }

        [$local, $domain] = explode('@', $email, 2);
        $local = (string) $local;
        $visible = mb_substr($local, 0, 1);

        return $visible.'***@'.$domain;
    }

    public static function maskPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) < 4) {
            return '****';
        }

        $last = substr($digits, -3);

        return '+****'.$last;
    }
}
