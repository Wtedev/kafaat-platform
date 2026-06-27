<?php

namespace App\Services\Privacy;

use Mews\Purifier\Facades\Purifier;

final class PrivacyPolicyHtmlSanitizer
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
            'HTML.Allowed' => 'h1,h2,h3,h4,p,br,ul,ol,li,strong,em,a[href|title|target],section,blockquote',
            'HTML.TargetBlank' => true,
            'AutoFormat.RemoveEmpty' => true,
        ];
    }

    public static function sanitize(string $html): string
    {
        return (string) Purifier::clean($html, self::config());
    }
}
