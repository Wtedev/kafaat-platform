<?php

namespace Database\Seeders;

use App\Enums\CertificateElementType;
use App\Enums\CertificateFieldKey;
use App\Enums\CertificateFontWeight;
use App\Enums\CertificateTextAlign;
use App\Models\CertificateTemplate;
use App\Services\Certificates\CertificateTemplateBackfill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * يجهّز قوالب الشهادات التي أنشأتها الترحيلات بخلفية تجريبية وعناصر ظاهرة.
 * الإصدار يدوي من لوحة الإدارة.
 */
class CertificateSeeder extends Seeder
{
    public function run(): void
    {
        $backfill = app(CertificateTemplateBackfill::class);
        $backfill->backfillTrainingPrograms();
        $backfill->backfillLearningPaths();
        $backfill->backfillVolunteerOpportunities();

        $png = $this->backgroundPng();
        $count = 0;

        CertificateTemplate::query()
            ->whereNull('background_path')
            ->orderBy('id')
            ->each(function (CertificateTemplate $template) use ($png, &$count): void {
                $path = 'certificate-backgrounds/'.$template->id.'/demo-background.png';
                Storage::disk('local')->put($path, $png);
                $template->update([
                    'background_path' => $path,
                    'background_disk' => 'local',
                    'page_width_mm' => 297,
                    'page_height_mm' => 210,
                    'elements' => $this->elements(),
                ]);
                $count++;
            });

        $this->command?->info('CertificateSeeder: جُهّز '.$count.' قالب شهادة بخلفية تجريبية.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function elements(): array
    {
        return [
            $this->fieldElement(CertificateFieldKey::RecipientName, 38, 22),
            $this->fieldElement(CertificateFieldKey::ActivityTitle, 52, 16),
            $this->fieldElement(CertificateFieldKey::IssueDateGregorian, 70, 12),
            $this->fieldElement(CertificateFieldKey::CertificateNumber, 82, 11),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldElement(CertificateFieldKey $key, float $y, float $size): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => CertificateElementType::Field->value,
            'key' => $key->value,
            'x' => 15,
            'y' => $y,
            'width' => 70,
            'height' => 8,
            'font_family' => 'ibmplexsansarabic',
            'font_size_pt' => $size,
            'font_weight' => CertificateFontWeight::Regular->value,
            'color' => '#1A1A1A',
            'align' => CertificateTextAlign::Center->value,
            'auto_shrink' => true,
            'min_font_size_pt' => 8,
        ];
    }

    private function backgroundPng(): string
    {
        $width = 1200;
        $height = 848;
        $image = imagecreatetruecolor($width, $height);
        $paper = imagecolorallocate($image, 252, 248, 240);
        $navy = imagecolorallocate($image, 31, 78, 121);
        $gold = imagecolorallocate($image, 184, 148, 74);
        imagefilledrectangle($image, 0, 0, $width, $height, $paper);
        imagesetthickness($image, 8);
        imagerectangle($image, 28, 28, $width - 29, $height - 29, $navy);
        imagesetthickness($image, 3);
        imagerectangle($image, 46, 46, $width - 47, $height - 47, $gold);

        ob_start();
        imagepng($image, null, 6);
        imagedestroy($image);
        $bytes = ob_get_clean();

        return is_string($bytes) ? $bytes : '';
    }
}
