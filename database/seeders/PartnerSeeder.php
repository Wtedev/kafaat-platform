<?php

namespace Database\Seeders;

use App\Models\Partner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Upserts partner rows and logos under partners/ only.
 * Never deletes or overwrites storage/app/public/news/images.
 */
class PartnerSeeder extends Seeder
{
    /**
     * @return list<array{name: string, type: string, sort_order: int, logo_file: string, website_url: ?string}>
     */
    private function partners(): array
    {
        return [
            [
                'name' => 'وزارة الموارد البشرية والتنمية الاجتماعية',
                'type' => 'حكومي',
                'sort_order' => 1,
                'logo_file' => 'hrsd.png',
                'website_url' => 'https://www.hrsd.gov.sa',
            ],
            [
                'name' => 'المركز الوطني لتنمية القطاع غير الربحي',
                'type' => 'شريك استراتيجي',
                'sort_order' => 2,
                'logo_file' => 'ncnp.png',
                'website_url' => 'https://ncnp.gov.sa',
            ],
            [
                'name' => 'جامعة القصيم',
                'type' => 'أكاديمي',
                'sort_order' => 3,
                'logo_file' => 'qu.png',
                'website_url' => 'https://www.qu.edu.sa',
            ],
            [
                'name' => 'الإدارة العامة للتعليم بمنطقة القصيم',
                'type' => 'حكومي',
                'sort_order' => 4,
                'logo_file' => 'moe-qassim.png',
                'website_url' => 'https://qassimedu.gov.sa',
            ],
            [
                'name' => 'مجلس الجمعيات الأهلية',
                'type' => 'شريك استراتيجي',
                'sort_order' => 5,
                'logo_file' => 'csa.png',
                'website_url' => null,
            ],
            [
                'name' => 'المجلس التخصصي للجمعيات الشبابية',
                'type' => 'شريك استراتيجي',
                'sort_order' => 6,
                'logo_file' => 'youth-ngos-council.png',
                'website_url' => null,
            ],
            [
                'name' => 'جامعة الملك سعود',
                'type' => 'أكاديمي',
                'sort_order' => 7,
                'logo_file' => 'ksu.png',
                'website_url' => 'https://ksu.edu.sa',
            ],
            [
                'name' => 'المؤسسة العامة للتدريب التقني والمهني',
                'type' => 'شريك استراتيجي',
                'sort_order' => 8,
                'logo_file' => 'tvtc.png',
                'website_url' => 'https://www.tvtc.gov.sa',
            ],
            [
                'name' => 'أوقاف الضحيان',
                'type' => 'شريك استراتيجي',
                'sort_order' => 9,
                'logo_file' => 'alduhian.png',
                'website_url' => null,
            ],
            [
                'name' => 'عبدالله الراجحي الخيرية',
                'type' => 'شريك استراتيجي',
                'sort_order' => 10,
                'logo_file' => 'alrajhi.png',
                'website_url' => null,
            ],
            [
                'name' => 'مؤسسة عبدالعزيز بن عبدالله الجميح الخيرية',
                'type' => 'شريك استراتيجي',
                'sort_order' => 11,
                'logo_file' => 'aljomaih.png',
                'website_url' => null,
            ],
            [
                'name' => 'غيث',
                'type' => 'شريك استراتيجي',
                'sort_order' => 12,
                'logo_file' => 'ghaith.png',
                'website_url' => null,
            ],
            [
                'name' => 'بيت الثقافة',
                'type' => 'شريك استراتيجي',
                'sort_order' => 13,
                'logo_file' => 'cultural-house.png',
                'website_url' => null,
            ],
        ];
    }

    public function run(): void
    {
        if (! Schema::hasTable('partners') || ! Schema::hasColumn('partners', 'type')) {
            $this->command?->warn('PartnerSeeder: table `partners` or column `type` is missing. Run migrations, then re-seed. Skipping.');

            return;
        }

        $keptNames = [];

        foreach ($this->partners() as $row) {
            $keptNames[] = $row['name'];
            $logoPath = $this->publishLogo($row['logo_file']);

            Partner::updateOrCreate(
                ['name' => $row['name']],
                [
                    'type' => $row['type'],
                    'logo' => $logoPath,
                    'website_url' => $row['website_url'],
                    'is_active' => true,
                    'sort_order' => $row['sort_order'],
                ]
            );
        }

        $removed = Partner::query()->whereNotIn('name', $keptNames)->delete();

        if ($removed > 0) {
            $this->command?->info("PartnerSeeder: removed {$removed} old partner records.");
        }
    }

    private function publishLogo(string $filename): ?string
    {
        $source = database_path("seeders/assets/partners/{$filename}");
        $relativePath = "partners/{$filename}";

        if (! File::exists($source)) {
            $this->command?->warn("PartnerSeeder: missing logo asset {$filename}.");

            return null;
        }

        Storage::disk('public')->makeDirectory('partners');

        Storage::disk('public')->put($relativePath, File::get($source));

        return $relativePath;
    }
}
