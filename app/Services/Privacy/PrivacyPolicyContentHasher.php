<?php

namespace App\Services\Privacy;

final class PrivacyPolicyContentHasher
{
    public static function normalize(string $content): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($content)) ?? trim($content);

        return $normalized;
    }

    public static function hash(string $content): string
    {
        return hash('sha256', self::normalize($content));
    }
}
