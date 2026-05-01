<?php

namespace App\Services\Portal;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exports competency HTML using mPDF for correct Arabic shaping + RTL.
 * DomPDF does not perform Arabic glyph joining; mPDF handles complex scripts better.
 */
final class CompetencyMpdfExporter
{
    public function stream(array $viewData, string $filename, ?string $asciiFallback = null): Response
    {
        $locale = $viewData['cvLocale'] ?? 'ar';
        $html = View::make('portal.competency-pdf-mpdf', $viewData)->render();

        $mpdf = $this->newMpdf($locale);
        $mpdf->WriteHTML($html);

        $pdfBinary = $mpdf->Output('', 'S');
        $fallback = $asciiFallback ?? str_replace('%', '', Str::ascii($filename));

        return new Response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', $filename, $fallback),
        ]);
    }

    private function newMpdf(string $locale = 'ar'): Mpdf
    {
        $defaultConfig = (new ConfigVariables)->getDefaults();
        $projectFontsDir = resource_path('fonts');
        $cairoRegular = $projectFontsDir.DIRECTORY_SEPARATOR.'Cairo-Regular.ttf';
        $cairoBold = $projectFontsDir.DIRECTORY_SEPARATOR.'Cairo-Bold.ttf';

        if (! is_readable($cairoRegular)) {
            throw new RuntimeException('mPDF Cairo font missing or unreadable: '.$cairoRegular);
        }
        if (! is_readable($cairoBold)) {
            throw new RuntimeException('mPDF Cairo font missing or unreadable: '.$cairoBold);
        }

        $fontDirs = array_merge([$projectFontsDir], $defaultConfig['fontDir']);

        $defaultFontConfig = (new FontVariables)->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];
        $rtl = $locale !== 'en';

        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 14,
            'margin_bottom' => 14,
            'tempDir' => $tempDir,
            'directionality' => $rtl ? 'rtl' : 'ltr',
            'autoScriptToLang' => true,
            // When true, mPDF picks bundled fonts per script/lang and ignores default_font for much of the output.
            'autoLangToFont' => false,
            'fontDir' => $fontDirs,
            'fontdata' => $fontData + [
                'cairo' => [
                    'R' => 'Cairo-Regular.ttf',
                    'B' => 'Cairo-Bold.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ],
            'default_font' => 'cairo',
            'defaultfooterline' => 0,
            'defaultfooterfontsize' => 0,
        ]);

        $mpdf->SetHTMLFooter('');

        return $mpdf;
    }
}
