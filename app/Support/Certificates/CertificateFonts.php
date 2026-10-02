<?php

namespace App\Support\Certificates;

/**
 * عائلات الخطوط الموجودة فعلياً في resources/fonts/certificates.
 */
final class CertificateFonts
{
    /**
     * @return list<string>
     */
    public static function families(): array
    {
        $directory = resource_path('fonts/certificates');
        $families = [];

        $files = array_merge(
            glob($directory.'/*.ttf') ?: [],
            glob($directory.'/*.otf') ?: [],
        );

        foreach ($files as $file) {
            $base = pathinfo($file, PATHINFO_FILENAME);
            $family = strtolower((string) preg_replace('/-(Regular|Bold|Light|Medium|SemiBold|Italic)$/i', '', $base));

            if ($family !== '') {
                $families[$family] = $family;
            }
        }

        $values = array_values($families);
        sort($values);

        return $values;
    }
}
