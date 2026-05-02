<?php

namespace Database\Seeders;

use App\Enums\OpportunityStatus;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $volunteeringManager = User::role('volunteering_manager')->first();

        if ($admin === null) {
            $this->command?->warn('SampleDataSeeder: admin not found — skipping volunteer seeding.');

            return;
        }

        $this->seedVolunteerOpportunities($admin, $volunteeringManager);
    }

    // ─── Volunteer opportunities ──────────────────────────────────────────────

    private function seedVolunteerOpportunities(User $admin, ?User $volunteeringManager): void
    {
        $opportunities = [
            [
                'title' => 'تعليم الكبار محو الأمية',
                'description' => 'فرصة تطوعية نبيلة لتعليم الكبار مهارات القراءة والكتابة الأساسية في مراكز التعليم المجتمعي.',
                'capacity' => 15,
                'hours_expected' => 40,
                'start_date' => now()->addDays(5),
                'end_date' => now()->addDays(65),
            ],
            [
                'title' => 'مساعدة ذوي الاحتياجات الخاصة',
                'description' => 'دعم وإسناد ذوي الاحتياجات الخاصة في مراكز الرعاية من خلال الأنشطة الترفيهية والتعليمية.',
                'capacity' => 10,
                'hours_expected' => 30,
                'start_date' => now()->addDays(3),
                'end_date' => now()->addDays(33),
            ],
            [
                'title' => 'توعية بيئية وتشجير',
                'description' => 'المشاركة في حملات التوعية البيئية وزرع الأشجار ضمن مبادرات المدينة الخضراء.',
                'capacity' => null,
                'hours_expected' => 20,
                'start_date' => now()->addDays(10),
                'end_date' => now()->addDays(40),
            ],
        ];

        foreach ($opportunities as $data) {
            $slug = Str::slug($data['title']) ?: 'volunteer-'.Str::random(6);

            VolunteerOpportunity::firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'capacity' => $data['capacity'],
                    'hours_expected' => $data['hours_expected'],
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'status' => OpportunityStatus::Published,
                    'published_at' => now()->subDays(rand(3, 15)),
                    'created_by' => $admin->id,
                    'assigned_to' => $volunteeringManager?->id,
                ]
            );
        }
    }
}
