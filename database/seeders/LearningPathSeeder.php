<?php

namespace Database\Seeders;

use App\Enums\PathStatus;
use App\Models\LearningPath;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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
                'description' => 'مسار تأسيسي يهدف إلى بناء مهارات التصميم الجرافيكي من الصفر، بدءًا من مبادئ التصميم، الألوان، التايبوغرافي، وصولًا إلى تطبيقات عملية باستخدام أدوات التصميم الحديثة، مع تنفيذ مشاريع تطبيقية تعزز المهارات العملية.',
            ],
            [
                'title' => 'مسار التأهيل المهني',
                'slug' => 'masar-altahil-almehnawi',
                'description' => 'مسار شامل يركز على تجهيز المشاركين لسوق العمل من خلال تطوير المهارات المهنية، كتابة السيرة الذاتية، اجتياز المقابلات، وفهم بيئة العمل.',
            ],
            [
                'title' => 'مسار اللغة الإنجليزية',
                'slug' => 'masar-allugha-alengliziyya',
                'description' => 'يهدف إلى تطوير مهارات اللغة الإنجليزية (استماع، تحدث، قراءة، كتابة) بما يخدم الاحتياج المهني والأكاديمي.',
            ],
            [
                'title' => 'مسار محلل البيانات',
                'slug' => 'masar-muhalil-albayanat',
                'description' => 'مسار متخصص لتأهيل المشاركين في مجال تحليل البيانات باستخدام أدوات مثل Excel وPower BI، مع التركيز على التحليل العملي واتخاذ القرار.',
            ],
        ];
    }

    public function run(): void
    {
        $owner = User::query()->where('email', 'lama.almeshiqeh@kafaat.org.sa')->first()
            ?? User::role('admin')->orderBy('id')->first();

        if ($owner === null) {
            $this->command?->error('LearningPathSeeder: no admin user found. Run UserSeeder first.');

            return;
        }

        foreach ($this->paths() as $row) {
            $slug = $row['slug'] ?: Str::slug($row['title']);

            LearningPath::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'capacity' => 120,
                    'status' => PathStatus::Published,
                    'published_at' => now()->subDays(14),
                    'created_by' => $owner->id,
                    'owner_id' => $owner->id,
                ]
            );
        }
    }
}
