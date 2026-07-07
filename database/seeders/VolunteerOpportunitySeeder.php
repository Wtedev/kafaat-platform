<?php

namespace Database\Seeders;

use App\Enums\OpportunityStatus;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class VolunteerOpportunitySeeder extends Seeder
{
    private const KEPT_SLUGS = [
        'nvg-26060074414',
        'nvg-26060067032',
    ];

    public function run(): void
    {
        $creator = User::query()->where('email', 'lama@kafaat.org.sa')->first()
            ?? User::role('admin')->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        $assignee = User::query()->where('email', 'eman.almutairi@kafaat.org.sa')->first()
            ?? $creator;

        if ($creator === null) {
            $this->command?->warn('VolunteerOpportunitySeeder: no users found; seeding opportunities without created_by.');
        }

        $items = [
            [
                'slug' => 'nvg-26060074414',
                'title' => 'المساهمة في تصميم الإعلانات التعريفية للقاءات الفريق التطوعي',
                'description' => implode("\n", [
                    'فرصة تطوعية تهدف إلى تصميم المواد إعلانية إبداعية للقاءات الفريق التطوعي بما يسهم في إبراز محتواها وتعزيز المشاركة فيها وفق الهوية المعتمدة.',
                    '',
                    'مجال التطوع: فني وإبداعي — تصميم الإعلانات',
                    'المهمة: المونتاج',
                    'نمط العمل: عن بُعد',
                    'رقم الفرصة على المنصة الوطنية: 26060074414',
                    'للتسجيل عبر المنصة الوطنية للعمل التطوعي:',
                    'https://nvg.gov.sa/opportunity/view-opportunity/c88e443e-f36b-1410-8b1e-00bab6d11711',
                ]),
                'capacity' => 2,
                'hours_expected' => 30.00,
                'start_date' => Carbon::create(2026, 6, 22),
                'end_date' => Carbon::create(2026, 6, 27),
            ],
            [
                'slug' => 'nvg-26060067032',
                'title' => 'المساهمة في تنفيذ مهام مبادرات الذكاء الاصطناعي',
                'description' => implode("\n", [
                    'فرصة تطوعية تتيح للمشاركين استكمال متطلبات المبادرة والمهام المرتبطة بها بما يسهم في تطوير المهارات والمعارف في مجال الذكاء الاصطناعي.',
                    '',
                    'مجال التطوع: تقنية المعلومات — الذكاء الاصطناعي',
                    'المهمة: إعداد التقارير',
                    'نمط العمل: عن بُعد',
                    'رقم الفرصة على المنصة الوطنية: 26060067032',
                    'للتسجيل عبر المنصة الوطنية للعمل التطوعي:',
                    'https://nvg.gov.sa/opportunity/view-opportunity/e019443e-f36b-1410-8b1e-00bab6d11711',
                ]),
                'capacity' => 50,
                'hours_expected' => 60.00,
                'start_date' => Carbon::create(2026, 6, 16),
                'end_date' => Carbon::create(2026, 6, 27),
            ],
        ];

        foreach ($items as $data) {
            VolunteerOpportunity::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'capacity' => $data['capacity'],
                    'hours_expected' => $data['hours_expected'],
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'status' => OpportunityStatus::Published,
                    'published_at' => now()->subDays(4),
                    'created_by' => $creator?->id,
                    'assigned_to' => $assignee?->id,
                ]
            );
        }

        $removed = VolunteerOpportunity::query()
            ->whereNotIn('slug', self::KEPT_SLUGS)
            ->delete();

        if ($removed > 0) {
            $this->command?->info("VolunteerOpportunitySeeder: removed {$removed} non-NVG volunteer opportunities.");
        }
    }
}
