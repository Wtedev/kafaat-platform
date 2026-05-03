<?php

namespace Database\Seeders;

use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Models\LearningPath;
use App\Models\TrainingProgram;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TrainingProgramSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->where('email', 'lama@kafaat.org.sa')->first()
            ?? User::role('admin')->orderBy('id')->first();

        $trainingCoordinator = User::query()->where('email', 'amna.albatti@kafaat.org.sa')->first();

        if ($owner === null) {
            $this->command?->error('TrainingProgramSeeder: owner user missing. Run AdminUserSeeder first.');

            return;
        }

        $y = 2026;

        foreach ($this->pathProgramRows($y) as $def) {
            $path = LearningPath::query()->where('slug', $def['path_slug'])->first();
            if ($path === null) {
                $this->command?->warn("TrainingProgramSeeder: path {$def['path_slug']} missing — skipped {$def['title']}.");

                continue;
            }

            $slug = $def['slug'] ?: Str::slug($def['title']);

            TrainingProgram::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $def['title'],
                    'description' => $def['description'],
                    'program_kind' => $def['kind'],
                    'capacity' => $def['capacity'],
                    'start_date' => $def['start_date'],
                    'end_date' => $def['end_date'],
                    'registration_start' => $def['registration_start'],
                    'registration_end' => $def['registration_end'],
                    'status' => ProgramStatus::Published,
                    'published_at' => now()->subDays(12),
                    'learning_path_id' => $path->id,
                    'path_sort_order' => $def['path_sort_order'],
                    'created_by' => $owner->id,
                    'owner_id' => $owner->id,
                    'assigned_to' => $trainingCoordinator?->id,
                ]
            );
        }

        foreach ($this->standaloneProgramRows($y) as $def) {
            $slug = $def['slug'] ?: Str::slug($def['title']);

            TrainingProgram::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $def['title'],
                    'description' => $def['description'],
                    'program_kind' => $def['kind'],
                    'capacity' => $def['capacity'],
                    'start_date' => $def['start_date'],
                    'end_date' => $def['end_date'],
                    'registration_start' => $def['registration_start'],
                    'registration_end' => $def['registration_end'],
                    'status' => ProgramStatus::Published,
                    'published_at' => now()->subDays(8),
                    'learning_path_id' => null,
                    'path_sort_order' => 0,
                    'created_by' => $owner->id,
                    'owner_id' => $owner->id,
                    'assigned_to' => $trainingCoordinator?->id,
                ]
            );
        }
    }

    /**
     * @return list<array{
     *   title: string,
     *   slug: string,
     *   description: string,
     *   kind: TrainingProgramKind,
     *   capacity: int,
     *   start_date: Carbon,
     *   end_date: Carbon,
     *   registration_start: Carbon,
     *   registration_end: Carbon,
     *   path_slug: string,
     *   path_sort_order: int
     * }>
     */
    private function pathProgramRows(int $y): array
    {
        $desc = fn (string $t) => "برنامج ضمن المسار التدريبي: {$t}. يجمع بين جلسات تطبيقية وتغذية راجعة لدعم الاستفادة في سياق عملي.";

        return [
            [
                'title' => 'أساسيات التصميم الجرافيكي',
                'slug' => 'asasiyat-altasim-algrafiki',
                'description' => $desc('أساسيات التصميم الجرافيكي'),
                'kind' => TrainingProgramKind::Course,
                'capacity' => 80,
                'start_date' => Carbon::create($y, 5, 4),
                'end_date' => Carbon::create($y, 7, 20),
                'registration_start' => Carbon::create($y, 2, 1),
                'registration_end' => Carbon::create($y, 8, 31),
                'path_slug' => 'masar-altasim-algrafiki-101',
                'path_sort_order' => 1,
            ],
            [
                'title' => 'مدخل إلى الهوية البصرية',
                'slug' => 'madkhal-ila-alhawiya-albasariyya',
                'description' => $desc('الهوية البصرية'),
                'kind' => TrainingProgramKind::Workshop,
                'capacity' => 45,
                'start_date' => Carbon::create($y, 6, 2),
                'end_date' => Carbon::create($y, 6, 26),
                'registration_start' => Carbon::create($y, 2, 10),
                'registration_end' => Carbon::create($y, 8, 31),
                'path_slug' => 'masar-altasim-algrafiki-101',
                'path_sort_order' => 2,
            ],
            [
                'title' => 'تصميم منشورات السوشال ميديا',
                'slug' => 'tasim-manshurat-sushial-midya',
                'description' => $desc('منشورات السوشال ميديا'),
                'kind' => TrainingProgramKind::Workshop,
                'capacity' => 50,
                'start_date' => Carbon::create($y, 7, 7),
                'end_date' => Carbon::create($y, 8, 18),
                'registration_start' => Carbon::create($y, 2, 15),
                'registration_end' => Carbon::create($y, 8, 31),
                'path_slug' => 'masar-altasim-algrafiki-101',
                'path_sort_order' => 3,
            ],
            [
                'title' => 'كتابة السيرة الذاتية باحتراف',
                'slug' => 'kitabat-alsirat-alzatiya-bihtiraf',
                'description' => $desc('السيرة الذاتية'),
                'kind' => TrainingProgramKind::Workshop,
                'capacity' => 90,
                'start_date' => Carbon::create($y, 4, 12),
                'end_date' => Carbon::create($y, 5, 24),
                'registration_start' => Carbon::create($y, 1, 5),
                'registration_end' => Carbon::create($y, 8, 31),
                'path_slug' => 'masar-altahil-lisooq-alamal',
                'path_sort_order' => 1,
            ],
            [
                'title' => 'مهارات المقابلة الشخصية',
                'slug' => 'maharat-almuqabala-alshakhsiyya',
                'description' => $desc('المقابلة الشخصية'),
                'kind' => TrainingProgramKind::Workshop,
                'capacity' => 85,
                'start_date' => Carbon::create($y, 5, 3),
                'end_date' => Carbon::create($y, 6, 14),
                'registration_start' => Carbon::create($y, 1, 8),
                'registration_end' => Carbon::create($y, 8, 31),
                'path_slug' => 'masar-altahil-lisooq-alamal',
                'path_sort_order' => 2,
            ],
            [
                'title' => 'بناء الملف المهني',
                'slug' => 'bina-almalaf-almehnawi',
                'description' => $desc('الملف المهني'),
                'kind' => TrainingProgramKind::Course,
                'capacity' => 70,
                'start_date' => Carbon::create($y, 5, 18),
                'end_date' => Carbon::create($y, 8, 10),
                'registration_start' => Carbon::create($y, 1, 12),
                'registration_end' => Carbon::create($y, 8, 31),
                'path_slug' => 'masar-altahil-lisooq-alamal',
                'path_sort_order' => 3,
            ],
            [
                'title' => 'مهارات التواصل في بيئة العمل',
                'slug' => 'maharat-altawasul-fi-biyat-alamal',
                'description' => $desc('التواصل في بيئة العمل'),
                'kind' => TrainingProgramKind::Course,
                'capacity' => 75,
                'start_date' => Carbon::create($y, 6, 1),
                'end_date' => Carbon::create($y, 9, 5),
                'registration_start' => Carbon::create($y, 1, 18),
                'registration_end' => Carbon::create($y, 8, 31),
                'path_slug' => 'masar-altahil-lisooq-alamal',
                'path_sort_order' => 4,
            ],
            [
                'title' => 'أساسيات البحث عن وظيفة',
                'slug' => 'asasiyat-albahth-an-wazhifa',
                'description' => $desc('البحث عن وظيفة'),
                'kind' => TrainingProgramKind::Workshop,
                'capacity' => 95,
                'start_date' => Carbon::create($y, 4, 22),
                'end_date' => Carbon::create($y, 6, 2),
                'registration_start' => Carbon::create($y, 1, 22),
                'registration_end' => Carbon::create($y, 8, 31),
                'path_slug' => 'masar-altahil-lisooq-alamal',
                'path_sort_order' => 5,
            ],
            [
                'title' => 'الاستعداد لأول يوم عمل',
                'slug' => 'al-istiidad-li-awwal-yawm-amal',
                'description' => $desc('أول يوم عمل'),
                'kind' => TrainingProgramKind::Session,
                'capacity' => 100,
                'start_date' => Carbon::create($y, 8, 8),
                'end_date' => Carbon::create($y, 8, 8),
                'registration_start' => Carbon::create($y, 2, 1),
                'registration_end' => Carbon::create($y, 8, 31),
                'path_slug' => 'masar-altahil-lisooq-alamal',
                'path_sort_order' => 6,
            ],
            [
                'title' => 'أساسيات القيادة التنفيذية',
                'slug' => 'asasiyat-alqiyada-altanfidhiyya',
                'description' => $desc('القيادة التنفيذية'),
                'kind' => TrainingProgramKind::Course,
                'capacity' => 55,
                'start_date' => Carbon::create($y, 5, 11),
                'end_date' => Carbon::create($y, 9, 15),
                'registration_start' => Carbon::create($y, 2, 5),
                'registration_end' => Carbon::create($y, 8, 31),
                'path_slug' => 'masar-alqaed-altanfidhi',
                'path_sort_order' => 1,
            ],
            [
                'title' => 'إدارة الفرق وبناء الثقافة',
                'slug' => 'idarat-alfuraq-wa-bina-althaqafa',
                'description' => $desc('إدارة الفرق'),
                'kind' => TrainingProgramKind::Bootcamp,
                'capacity' => 40,
                'start_date' => Carbon::create($y, 6, 1),
                'end_date' => Carbon::create($y, 9, 30),
                'registration_start' => Carbon::create($y, 2, 8),
                'registration_end' => Carbon::create($y, 8, 31),
                'path_slug' => 'masar-alqaed-altanfidhi',
                'path_sort_order' => 2,
            ],
            [
                'title' => 'التخطيط الاستراتيجي للقادة',
                'slug' => 'altakhtit-alastratiji-lilqada',
                'description' => $desc('التخطيط الاستراتيجي'),
                'kind' => TrainingProgramKind::Workshop,
                'capacity' => 48,
                'start_date' => Carbon::create($y, 7, 6),
                'end_date' => Carbon::create($y, 8, 24),
                'registration_start' => Carbon::create($y, 2, 12),
                'registration_end' => Carbon::create($y, 8, 31),
                'path_slug' => 'masar-alqaed-altanfidhi',
                'path_sort_order' => 3,
            ],
            [
                'title' => 'اتخاذ القرار وحل المشكلات',
                'slug' => 'itkhadh-alqarar-wa-hall-almushkilat',
                'description' => $desc('اتخاذ القرار'),
                'kind' => TrainingProgramKind::Workshop,
                'capacity' => 50,
                'start_date' => Carbon::create($y, 7, 20),
                'end_date' => Carbon::create($y, 9, 10),
                'registration_start' => Carbon::create($y, 2, 18),
                'registration_end' => Carbon::create($y, 8, 31),
                'path_slug' => 'masar-alqaed-altanfidhi',
                'path_sort_order' => 4,
            ],
            [
                'title' => 'إدارة الأداء ومؤشرات القياس',
                'slug' => 'idarat-alada-ma-muashirat-alqiyas',
                'description' => $desc('إدارة الأداء'),
                'kind' => TrainingProgramKind::Course,
                'capacity' => 52,
                'start_date' => Carbon::create($y, 8, 3),
                'end_date' => Carbon::create($y, 10, 12),
                'registration_start' => Carbon::create($y, 2, 22),
                'registration_end' => Carbon::create($y, 8, 31),
                'path_slug' => 'masar-alqaed-altanfidhi',
                'path_sort_order' => 5,
            ],
            [
                'title' => 'مهارات العرض والتأثير',
                'slug' => 'maharat-alard-wa-altathir',
                'description' => $desc('العرض والتأثير'),
                'kind' => TrainingProgramKind::Workshop,
                'capacity' => 46,
                'start_date' => Carbon::create($y, 9, 7),
                'end_date' => Carbon::create($y, 10, 28),
                'registration_start' => Carbon::create($y, 3, 1),
                'registration_end' => Carbon::create($y, 8, 31),
                'path_slug' => 'masar-alqaed-altanfidhi',
                'path_sort_order' => 6,
            ],
        ];
    }

    /**
     * @return list<array{
     *   title: string,
     *   slug: string,
     *   description: string,
     *   kind: TrainingProgramKind,
     *   capacity: int,
     *   start_date: Carbon,
     *   end_date: Carbon,
     *   registration_start: Carbon,
     *   registration_end: Carbon
     * }>
     */
    private function standaloneProgramRows(int $y): array
    {
        $rows = [
            ['title' => 'لقاء الابتكار وريادة الأعمال', 'kind' => TrainingProgramKind::Session],
            ['title' => 'لقاء كيف تصنع من البيانات قيمة', 'kind' => TrainingProgramKind::Session],
            ['title' => 'لقاء كيف أختار مساري المهني', 'kind' => TrainingProgramKind::Session],
            ['title' => 'ورشة عمل مدخل إلى ريادة الأعمال', 'kind' => TrainingProgramKind::Workshop],
            ['title' => 'ورشة عمل مدخل البيانات المحترف', 'kind' => TrainingProgramKind::Workshop],
            ['title' => 'ورشة عمل أساسيات التفكير البصري', 'kind' => TrainingProgramKind::Workshop],
            ['title' => 'ورشة عمل نمطك هو البوصلة', 'kind' => TrainingProgramKind::Workshop],
            ['title' => 'ورشة عمل الخط العربي', 'kind' => TrainingProgramKind::Workshop],
            ['title' => 'ورشة عمل بناء خطة شخصية للتطوير', 'kind' => TrainingProgramKind::Workshop],
            ['title' => 'دورة تدريبية: كتابة البرومبت باحترافية', 'kind' => TrainingProgramKind::Course],
            ['title' => 'دورة تدريبية: فريمر من الصفر', 'kind' => TrainingProgramKind::Course],
            ['title' => 'دورة تدريبية: أول واجهة في فيقما', 'kind' => TrainingProgramKind::Course],
            ['title' => 'دورة تدريبية: إدارة المحتوى في وسائل التواصل الاجتماعي', 'kind' => TrainingProgramKind::Course],
            ['title' => 'دورة تدريبية: مؤشرات الأداء', 'kind' => TrainingProgramKind::Course],
        ];

        $out = [];
        foreach ($rows as $i => $row) {
            $slug = Str::slug($row['title']);
            if ($slug === '') {
                $slug = 'standalone-seed-'.$i;
            }
            $start = Carbon::create($y, 4 + (($i * 3) % 6), 1 + ($i % 20));
            $end = (clone $start)->addDays(match ($row['kind']) {
                TrainingProgramKind::Session => 0,
                TrainingProgramKind::Workshop => 4,
                default => 25,
            });

            $out[] = [
                'title' => $row['title'],
                'slug' => $slug,
                'description' => 'برنامج مستقل في كتالوج كفاءات: '.$row['title'].' — محتوى عملي وتواريخ واضحة للتسجيل والحضور.',
                'kind' => $row['kind'],
                'capacity' => 55 + ($i * 3) % 40,
                'start_date' => $start,
                'end_date' => $end,
                'registration_start' => Carbon::create($y, 1, 5),
                'registration_end' => Carbon::create($y, 12, 20),
            ];
        }

        return $out;
    }
}
