<?php

namespace App\Services\Certificates;

use App\Data\Certificates\CertificateElement;
use App\Enums\CertificateElementType;
use App\Enums\CertificateFieldKey;
use App\Enums\CertificateFontWeight;
use App\Models\CertificateTemplate;
use Endroid\QrCode\Builder\Builder;
use Illuminate\Support\Facades\Storage;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * يرسم قالب الشهادة إلى PDF بنفس أصل الإحداثيات: x من يسار الصفحة، و y أعلى الصندوق، كنسب مئوية.
 */
class CertificateRenderer
{
    /**
     * @param  array<string, scalar|null>  $fieldValues
     * @param  list<CertificateElement>|null  $elements
     */
    public function render(CertificateTemplate $template, array $fieldValues, ?array $elements = null): string
    {
        $pageWidth = (float) $template->page_width_mm;
        $pageHeight = (float) $template->page_height_mm;
        $mpdf = $this->newMpdf($pageWidth, $pageHeight);

        $background = $this->backgroundFile($template);
        if ($background !== null) {
            $mpdf->Image($background, 0, 0, $pageWidth, $pageHeight, '', '', true, false);
        }

        foreach ($elements ?? $template->elements as $element) {
            if (! $element instanceof CertificateElement) {
                continue;
            }

            $x = ($element->x / 100) * $pageWidth;
            $y = ($element->y / 100) * $pageHeight;
            $width = ($element->width / 100) * $pageWidth;
            $height = ($element->height / 100) * $pageHeight;

            if ($element->type === CertificateElementType::Qr) {
                $this->drawQr($mpdf, $fieldValues, $x, $y, $width, $height);

                continue;
            }

            if ($element->type === CertificateElementType::Image) {
                $this->drawImage($mpdf, $element, $x, $y, $width, $height);

                continue;
            }

            $this->drawText($mpdf, $element, $fieldValues, $x, $y, $width, $height);
        }

        if ($background !== null && str_starts_with($background, sys_get_temp_dir())) {
            @unlink($background);
        }

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * @return array<string, string>
     */
    public function sampleValues(): array
    {
        $values = [];

        foreach (CertificateFieldKey::cases() as $key) {
            $values[$key->value] = $key->sample();
        }

        return $values;
    }

    private function newMpdf(float $pageWidth, float $pageHeight): Mpdf
    {
        $fontDir = resource_path('fonts/certificates');
        $defaultConfig = (new ConfigVariables)->getDefaults();
        $fontDirs = array_merge([$fontDir], $defaultConfig['fontDir']);
        $fontdata = (new FontVariables)->getDefaults()['fontdata'];
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
            'format' => [$pageWidth, $pageHeight],
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'fontDir' => $fontDirs,
            'fontdata' => $fontdata,
            'default_font' => 'ibmplexsansarabic',
            'directionality' => 'rtl',
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
            'tempDir' => $tempDir,
        ]);
    }

    /**
     * @param  array<string, scalar|null>  $fieldValues
     */
    private function drawText(Mpdf $mpdf, CertificateElement $element, array $fieldValues, float $x, float $y, float $width, float $height): void
    {
        $text = $this->textFor($element, $fieldValues);
        $size = $this->fittedSize($mpdf, $element, $text, $width);
        $weight = $element->fontWeight === CertificateFontWeight::Bold ? 'bold' : 'normal';
        $align = $element->align?->value ?? 'center';
        $color = $element->color ?? '#1a1a1a';
        $family = $element->fontFamily ?? 'ibmplexsansarabic';
        $html = '<div style="font-family: '.e($family).'; font-size: '.$size.'pt; font-weight: '.$weight.'; color: '.e($color).'; text-align: '.e($align).'; direction: rtl; line-height: 1.15;">'.e($text).'</div>';

        $mpdf->WriteFixedPosHTML($html, $x, $y, $width, $height, 'hidden');
    }

    /**
     * @param  array<string, scalar|null>  $fieldValues
     */
    private function textFor(CertificateElement $element, array $fieldValues): string
    {
        if ($element->type === CertificateElementType::Text) {
            return (string) ($element->text ?? '');
        }

        $value = (string) ($fieldValues[$element->key?->value ?? ''] ?? '');

        return ($element->prefix ?? '').$value.($element->suffix ?? '');
    }

    /**
     * @param  array<string, scalar|null>  $fieldValues
     */
    private function fittedSize(Mpdf $mpdf, CertificateElement $element, string $text, float $widthMm): float
    {
        $size = (float) ($element->fontSizePt ?? 16);
        $min = (float) ($element->minFontSizePt ?? $size);

        if (! $element->autoShrink || $text === '' || $min >= $size) {
            return $size;
        }

        $style = $element->fontWeight === CertificateFontWeight::Bold ? 'B' : '';
        $family = $element->fontFamily ?? 'ibmplexsansarabic';

        while ($size > $min) {
            $mpdf->SetFont($family, $style, $size);
            if ($mpdf->GetStringWidth($text) <= $widthMm) {
                break;
            }
            $size = round($size - 0.5, 2);
        }

        return max($size, $min);
    }

    /**
     * @param  array<string, scalar|null>  $fieldValues
     */
    private function drawQr(Mpdf $mpdf, array $fieldValues, float $x, float $y, float $width, float $height): void
    {
        $code = (string) ($fieldValues[CertificateFieldKey::VerificationCode->value] ?? '');
        $url = $code !== ''
            ? route('certificates.verify', ['code' => $code])
            : url('/');
        $png = (new Builder(data: $url, size: 280, margin: 0))->build()->getString();
        $temp = tempnam(sys_get_temp_dir(), 'cqr');
        if ($temp === false) {
            return;
        }

        $file = $temp.'.png';
        file_put_contents($file, $png);
        @unlink($temp);
        $mpdf->Image($file, $x, $y, $width, $height, 'png', '', true, false);
        @unlink($file);
    }

    private function drawImage(Mpdf $mpdf, CertificateElement $element, float $x, float $y, float $width, float $height): void
    {
        $path = $element->imagePath;
        if ($path === null || str_contains($path, '..')) {
            return;
        }

        $absolute = $this->localFile('local', $path);
        if ($absolute === null) {
            return;
        }

        $mpdf->Image($absolute, $x, $y, $width, $height, '', '', true, false);
        if (str_starts_with($absolute, sys_get_temp_dir())) {
            @unlink($absolute);
        }
    }

    private function backgroundFile(CertificateTemplate $template): ?string
    {
        $path = $template->background_path;
        if ($path === null || $path === '') {
            return null;
        }

        return $this->localFile($template->background_disk ?: 'local', $path);
    }

    private function localFile(string $disk, string $path): ?string
    {
        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            return null;
        }

        try {
            return $storage->path($path);
        } catch (\Throwable) {
            $temp = tempnam(sys_get_temp_dir(), 'cbg');
            if ($temp === false) {
                return null;
            }
            file_put_contents($temp, $storage->get($path));

            return $temp;
        }
    }
}
