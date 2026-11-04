<?php

namespace Database\Seeders;

use App\Enums\OpportunityStatus;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use Illuminate\Database\Seeder;

class VolunteerOpportunitySeeder extends Seeder
{
    /**
     * The only volunteer opportunities on the platform. Anything else is deleted
     * (with its registrations) on deploy. Registration happens on the National
     * Volunteering Platform via external_url, so no login is needed here.
     */
    private const KEPT_SLUGS = [
        'nvg-26110305329',
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
                'slug' => 'nvg-26110305329',
                'title' => 'بالموهبة نخدم الوطن - التصميم والإنتاج المرئي',
                'description' => implode("\n", [
                    'المساهمة في إنتاج محتوى وطني إبداعي من خلال الاستفادة من مهارات المتطوعين في التصميم الجرافيكي، وصناعة الفيديو، والمونتاج؛ بهدف تعزيز قيم الانتماء والولاء والاعتزاز بالوطن، وإنتاج مواد مرئية وإبداعية وفق معايير الهوية البصرية المعتمدة، ضمن مبادرة بالموهبة نخدم الوطن.',
                    '',
                    'مجال التطوع: فني وإبداعي — تصميم الإعلام والمطبوعات (التصميم الجرافيكي)',
                    'المهام: المونتاج، التصميم',
                    'نمط العمل: عن بُعد',
                    'الوقت: من 9:00 صباحًا إلى 2:00 ظهرًا',
                    'المهارات المطلوبة: التفكير الإبداعي والابتكار، التواصل الفعّال، الالتزام والانضباط',
                    'يحصل المتطوع على: تدريب',
                    'الفئة العمرية: 15 - 90 سنة — للجنسين — مناسبة لذوي الإعاقة — بدون مقابلة شخصية',
                    'رقم الفرصة على المنصة الوطنية: 26110305329',
                ]),
                'external_url' => 'https://nvg.gov.sa/opportunity/view-opportunity/f71b493e-f36b-1410-8b1f-00bab6d11711',
                'capacity' => 50,
                'hours_expected' => 60.00,
                // Y-m-d strings: Carbon midnight in Asia/Riyadh can persist a day early in PostgreSQL DATE columns.
                'start_date' => '2026-11-14',
                'end_date' => '2026-11-25',
            ],
        ];

        foreach ($items as $data) {
            VolunteerOpportunity::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'external_url' => $data['external_url'],
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

        // Delete one by one so each model's deleting hook also removes its image.
        // Registrations are removed by the database (cascadeOnDelete).
        $removed = 0;
        VolunteerOpportunity::query()
            ->whereNotIn('slug', self::KEPT_SLUGS)
            ->each(function (VolunteerOpportunity $opportunity) use (&$removed): void {
                $opportunity->delete();
                $removed++;
            });

        if ($removed > 0) {
            $this->command?->info("VolunteerOpportunitySeeder: removed {$removed} old volunteer opportunities.");
        }
    }
}
