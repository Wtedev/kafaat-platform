<?php

namespace Database\Seeders;

use App\Models\Partner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PartnerSeeder extends Seeder
{
    /**
     * @return list<array{name: string, type: string, sort_order: int}>
     */
    private function partners(): array
    {
        return [
            ['name' => 'وزارة الموارد البشرية', 'type' => 'حكومي', 'sort_order' => 1],
            ['name' => 'صندوق دعم الجمعيات', 'type' => 'شريك استراتيجي', 'sort_order' => 2],
            ['name' => 'صلة', 'type' => 'شريك استراتيجي', 'sort_order' => 3],
            ['name' => 'جامعة القصيم', 'type' => 'أكاديمي', 'sort_order' => 4],
            ['name' => 'جامعة المستقبل', 'type' => 'أكاديمي', 'sort_order' => 5],
        ];
    }

    public function run(): void
    {
        if (! Schema::hasTable('partners') || ! Schema::hasColumn('partners', 'type')) {
            $this->command?->warn('PartnerSeeder: table `partners` or column `type` is missing. Run migrations, then re-seed. Skipping.');

            return;
        }

        foreach ($this->partners() as $row) {
            Partner::updateOrCreate(
                ['name' => $row['name']],
                [
                    'type' => $row['type'],
                    'logo' => null,
                    'website_url' => null,
                    'is_active' => true,
                    'sort_order' => $row['sort_order'],
                ]
            );
        }
    }
}
