<?php

namespace Database\Seeders;

use App\Enums\LearningPathKind;
use App\Enums\PathStatus;
use App\Models\LearningPath;
use App\Models\User;
use Illuminate\Database\Seeder;

class LearningPathSeeder extends Seeder
{
    /**
     * @return list<array{title: string, slug: string, description: string}>
     */
    private function paths(): array
    {
        return [
            [
                'title' => 'مسار التصميم الجرافيكي 101',
                'slug' => 'masar-altasim-algrafiki-101',
                'description' => 'مسار تأسيسي يهدف إلى بناء مهارات التصميم الجرافيكي من الصفر، بدءًا من مبادئ التصميم والألوان والتايبوغرافيا، وصولًا إلى تطبيقات عملية لمنشورات رقمية وهوية بصرية.',
            ],
            [
                'title' => 'مسار التأهيل لسوق العمل',
                'slug' => 'masar-altahil-lisooq-alamal',
                'description' => 'مسار يركز على الجاهزية المهنية: السيرة الذاتية، المقابلات، التواصل في بيئة العمل، والبحث عن فرص — بربط مباشر بممارسات سوق العمل.',
            ],
            [
                'title' => 'مسار القائد التنفيذي',
                'slug' => 'masar-alqaed-altanfidhi',
                'description' => 'مسار للمهتمين بالقيادة التنفيذية: التخطيط، إدارة الفرق، اتخاذ القرار، ومؤشرات الأداء — بلغة تطبيقية قريبة من المؤسسات.',
            ],
        ];
    }

    public function run(): void
    {
        $owner = User::query()->where('email', 'lama@kafaat.org.sa')->first()
            ?? User::role('admin')->orderBy('id')->first();

        if ($owner === null) {
            $this->command?->error('LearningPathSeeder: no admin user found. Run AdminUserSeeder first.');

            return;
        }

        foreach ($this->paths() as $row) {
            LearningPath::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'path_kind' => LearningPathKind::TrainingPath,
                    'capacity' => 200,
                    'status' => PathStatus::Published,
                    'published_at' => now()->subDays(20),
                    'created_by' => $owner->id,
                    'owner_id' => $owner->id,
                ]
            );
        }
    }
}
