<?php

namespace App\Services;

use App\Enums\CertificateFieldKey;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\TrainingProgram;
use App\Services\Certificates\CertificateFieldValues;
use App\Services\Certificates\CertificateQr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class CertificatePdfService
{
    private const PAGE_WIDTH_MM = 297.0;

    private const PAGE_HEIGHT_MM = 210.0;

    /**
     * Generate a PDF for the given certificate and persist it to the public disk at
     * certificates/{certificate_number}.pdf (under storage/app/public).
     *
     * A training program with a saved background uses that design. Every other
     * certificate keeps the original IBM Plex template.
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
            $binary = $this->render($certificate);
            if (file_put_contents($fullPath, $binary) === false) {
                throw new RuntimeException('Unable to store the certificate PDF.');
            }
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

    public function previewResponse(TrainingProgram $program): Response
    {
        try {
            $binary = $this->renderPreview($program);
        } catch (RuntimeException) {
            abort(422, 'احفظ تصميم الشهادة أولاً.');
        }

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certificate-preview.pdf"',
        ]);
    }

    /**
     * Write a sample preview PDF for visual comparison. No certificate row is created.
     */
    public function writeSample(TrainingProgram $program): string
    {
        $relative = 'certificate-samples/'.$program->getKey().'.pdf';
        Storage::disk('local')->put($relative, $this->renderPreview($program));

        return Storage::disk('local')->path($relative);
    }

    public function renderPreview(TrainingProgram $program): string
    {
        $template = $this->savedDesign($program);
        if (! $template instanceof CertificateTemplate) {
            throw new RuntimeException('Save a certificate design with a background before rendering a preview.');
        }

        return $this->renderDesign($template, CertificateFieldValues::forPreview($program));
    }

    private function render(Certificate $certificate): string
    {
        $program = $certificate->certificateable;
        if ($program instanceof TrainingProgram) {
            $template = $this->savedDesign($program);
            if ($template instanceof CertificateTemplate) {
                return $this->renderDesign($template, CertificateFieldValues::forCertificate($certificate));
            }
        }

        return $this->renderLegacy($certificate);
    }

    private function savedDesign(TrainingProgram $program): ?CertificateTemplate
    {
        $program->loadMissing('certificateTemplate');
        $template = $program->certificateTemplate;
        if (! $template instanceof CertificateTemplate || blank($template->background_path)) {
            return null;
        }

        if (! Storage::disk('public')->exists($template->background_path)) {
            return null;
        }

        return $template;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function renderDesign(CertificateTemplate $template, array $values): string
    {
        $mpdf = $this->newMpdf(true);
        // Image() writes straight into the document buffer until a page is open,
        // which shifts the cross-reference table and makes the PDF unreadable.
        $mpdf->AddPage();
        $source = Storage::disk('public')->path((string) $template->background_path);
        $background = $this->jpegForPdf($source);
        try {
            $mpdf->Image($background, 0, 0, self::PAGE_WIDTH_MM, self::PAGE_HEIGHT_MM, '', '', true, false);
        } finally {
            if ($background !== $source && is_file($background)) {
                unlink($background);
            }
        }

        foreach ($template->elements ?? [] as $key => $element) {
            if (! is_string($key) || ! is_array($element) || ! array_key_exists($key, $values)) {
                continue;
            }

            $field = CertificateFieldKey::tryFrom($key);
            if ($field === null) {
                continue;
            }

            if ($field->isQr()) {
                $this->drawQr($mpdf, $element, $values[$key]);

                continue;
            }

            $this->drawText($mpdf, $element, $values[$key]);
        }

        return $this->binary($mpdf);
    }

    private function renderLegacy(Certificate $certificate): string
    {
        $mpdf = $this->newMpdf(false);
        $mpdf->WriteHTML(view('certificates.certificate', [
            'certificate' => $certificate,
        ])->render());

        return $this->binary($mpdf);
    }

    private function binary(Mpdf $mpdf): string
    {
        $binary = $mpdf->Output('', Destination::STRING_RETURN);
        if (! str_starts_with($binary, '%PDF')) {
            throw new RuntimeException('mPDF did not produce a PDF.');
        }

        return $binary;
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private function drawText(Mpdf $mpdf, array $element, string $text): void
    {
        if ($text === '') {
            return;
        }

        $width = self::PAGE_WIDTH_MM * $this->bounded($element['width'] ?? 70, 1, 100) / 100;
        $centerX = self::PAGE_WIDTH_MM * $this->bounded($element['x'] ?? 50, 0, 100) / 100;
        $centerY = self::PAGE_HEIGHT_MM * $this->bounded($element['y'] ?? 50, 0, 100) / 100;
        $size = $this->bounded($element['size'] ?? 16, 8, 96);
        // Same model as the designer canvas: a line box of font-size × 1.2, centered on (x, y).
        $boxHeight = ($size * 25.4 / 72) * 1.2;
        $font = ($element['font'] ?? 'shamel') === 'ibmplex' ? 'ibmplexsansarabic' : 'shamel';
        $weight = ($element['weight'] ?? 'regular') === 'bold' ? 'bold' : 'normal';
        $align = in_array($element['align'] ?? 'center', ['right', 'center', 'left'], true)
            ? $element['align']
            : 'center';
        $color = is_string($element['color'] ?? null) && preg_match('/^#[0-9A-Fa-f]{6}$/', $element['color']) === 1
            ? $element['color']
            : '#1a1a1a';
        $safe = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<div style="font-family: '.$font.'; font-weight: '.$weight.'; font-size: '.$size.'pt; color: '.$color.'; text-align: '.$align.'; direction: rtl; line-height: 1.2;">'.$safe.'</div>';

        $mpdf->WriteFixedPosHTML(
            $html,
            $centerX - ($width / 2),
            $centerY - ($boxHeight / 2),
            $width,
            $boxHeight,
            // 'auto' shrinks the font until the text fits the one-line box, so a long
            // name stays on one line inside the width set in the designer instead of
            // wrapping onto the design below it.
            'auto',
        );
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private function drawQr(Mpdf $mpdf, array $element, string $url): void
    {
        if ($url === '') {
            return;
        }

        $size = $this->bounded($element['size'] ?? 28, 8, 96);
        $centerX = self::PAGE_WIDTH_MM * $this->bounded($element['x'] ?? 50, 0, 100) / 100;
        $centerY = self::PAGE_HEIGHT_MM * $this->bounded($element['y'] ?? 50, 0, 100) / 100;
        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $temporary = $tempDir.'/qr-'.bin2hex(random_bytes(8)).'.png';
        file_put_contents($temporary, CertificateQr::png($url));

        try {
            $mpdf->Image($temporary, $centerX - ($size / 2), $centerY - ($size / 2), $size, $size, 'png', '', true, false);
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /**
     * Chrome's PDF viewer paints a black page for a full-bleed PNG stored with
     * a PNG predictor. A JPEG uses DCTDecode, which that viewer can paint.
     */
    private function jpegForPdf(string $path): string
    {
        $mime = mime_content_type($path);
        if ($mime === 'image/jpeg') {
            return $path;
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));
        if ($image === false) {
            return $path;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $flat = imagecreatetruecolor($width, $height);
        if ($flat === false) {
            imagedestroy($image);

            return $path;
        }

        $white = imagecolorallocate($flat, 255, 255, 255);
        imagefilledrectangle($flat, 0, 0, $width, $height, $white);
        imagecopy($flat, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);

        $temporary = storage_path('app/mpdf/bg-'.bin2hex(random_bytes(8)).'.jpg');
        if (! is_dir(dirname($temporary))) {
            mkdir(dirname($temporary), 0755, true);
        }
        imagejpeg($flat, $temporary, 90);
        imagedestroy($flat);

        return $temporary;
    }

    private function bounded(mixed $value, float $min, float $max): float
    {
        $number = is_numeric($value) ? (float) $value : $min;

        return min($max, max($min, $number));
    }

    private function newMpdf(bool $fullBleed): Mpdf
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
        $fontdata['shamel'] = [
            'R' => 'FFShamelFamily-SansOneBook.ttf',
            'B' => 'FFShamelFamily-SansOneBold.ttf',
            'useOTL' => 0xFF,
            'useKashida' => 75,
        ];

        $tempDir = storage_path('app/mpdf');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $margin = $fullBleed ? 0 : 10;

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'L',
            'margin_left' => $margin,
            'margin_right' => $margin,
            'margin_top' => $margin,
            'margin_bottom' => $margin,
            'fontDir' => $fontDirs,
            'fontdata' => $fontdata,
            'default_font' => 'ibmplexsansarabic',
            'directionality' => 'rtl',
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
            // Shamel has no Latin letters, so a number like CERT-20261027-AB12CD34
            // borrows the missing glyphs from IBM Plex instead of printing boxes.
            'useSubstitutions' => true,
            'backupSubsFont' => ['ibmplexsansarabic'],
            'tempDir' => $tempDir,
        ]);
    }
}
