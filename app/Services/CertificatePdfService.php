<?php

namespace App\Services;

use App\Models\Certificate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class CertificatePdfService
{
    /**
     * Generate a PDF for the given certificate and persist it to the public disk at
     * certificates/{certificate_number}.pdf (under storage/app/public).
     *
     * Uses mPDF (not DomPDF) for correct Arabic shaping, RTL, and IBM Plex Sans Arabic.
     *
     * @return string Path relative to the public disk root (e.g. certificates/CERT-....pdf).
     */
    public function generate(Certificate $certificate): string
    {
        $certificate->loadMissing(['user.profile', 'certificateable']);

        $storagePath = 'certificates/'.$certificate->certificate_number.'.pdf';
        Storage::disk('public')->makeDirectory('certificates');
        $fullPath = Storage::disk('public')->path($storagePath);

        try {
            $mpdf = $this->newMpdf();
            $html = view('certificates.certificate', [
                'certificate' => $certificate,
            ])->render();

            $mpdf->WriteHTML($html);
            $mpdf->Output($fullPath, Destination::FILE);
        } catch (\Throwable $e) {
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
            Log::error('Certificate PDF generation failed', [
                'certificate_id' => $certificate->getKey(),
                'certificate_number' => $certificate->certificate_number,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $storagePath;
    }

    /**
     * Re-generate the PDF for an already-issued certificate (e.g. after a template change).
     */
    public function regenerate(Certificate $certificate): string
    {
        $path = $this->generate($certificate);
        $certificate->update(['file_path' => $path]);

        return $path;
    }

    private function newMpdf(): Mpdf
    {
        $fontDir = resource_path('fonts/certificates');

        $defaultConfig = (new ConfigVariables)->getDefaults();
        $fontDirs = array_merge([$fontDir], $defaultConfig['fontDir']);

        $defaultFontConfig = (new FontVariables)->getDefaults();
        $fontdata = $defaultFontConfig['fontdata'];
        $fontdata['ibmplexsansarabic'] = [
            'R' => 'IBMPlexSansArabic-Regular.ttf',
            'B' => 'IBMPlexSansArabic-Bold.ttf',
            'useOTL' => 0xFF,
            'useKashida' => 75,
        ];

        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'fontDir' => $fontDirs,
            'fontdata' => $fontdata,
            'default_font' => 'ibmplexsansarabic',
            'directionality' => 'rtl',
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
            'tempDir' => $tempDir,
        ]);
    }
}
