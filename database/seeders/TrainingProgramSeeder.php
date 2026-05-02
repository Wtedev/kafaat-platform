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
        $owner = User::query()->where('email', 'lama.almeshiqeh@kafaat.org.sa')->first()
            ?? User::role('admin')->orderBy('id')->first();

        $trainingManager = User::query()->where('email', 'amna.albatti@kafaat.org.sa')->first();

        if ($owner === null) {
            $this->command?->error('TrainingProgramSeeder: owner user missing. Run UserSeeder first.');

            return;
        }

        $programs = $this->programDefinitions();

        foreach ($programs as $def) {
            $path = LearningPath::query()->where('slug', $def['path_slug'])->first();

            if ($path === null) {
                $this->command?->warn("TrainingProgramSeeder: path {$def['path_slug']} not found — skipping {$def['title']}.");

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
                    'published_at' => now()->subDays(10),
                    'learning_path_id' => $path->id,
                    'path_sort_order' => $def['path_sort_order'],
                    'created_by' => $owner->id,
                    'owner_id' => $owner->id,
                    'assigned_to' => $trainingManager?->id,
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
    private function programDefinitions(): array
    {
        $y = (int) now()->year;

        return [
            [
                'title' => 'قادة',
                'slug' => 'qadah',
                'description' => 'برنامج يهدف إلى صقل مهارات القيادة المباشرة وتمكين المشاركين من التأثير الإيجابي في فرق العمل، عبر ورش عملية ومشاريع تعزز الثقة والمساءلة واتخاذ القرار الجماعي.',
                'kind' => TrainingProgramKind::Bootcamp,
                'capacity' => 45,
                'start_date' => Carbon::create($y, 4, 6),
                'end_date' => Carbon::create($y, 6, 18),
                'registration_start' => Carbon::create($y, 2, 1),
                'registration_end' => Carbon::create($y, 3, 25),
                'path_slug' => 'masar-altahil-almehnawi',
                'path_sort_order' => 1,
            ],
            [
                'title' => 'سند',
                'slug' => 'sanad',
                'description' => 'مسار دعم يعزز الاستقرار الوظيفي من خلال التوجيه المهني، تطوير المهارات اللينة، وبناء خطة تنمية فردية تربط أهداف المشارك بفرص سوق العمل.',
                'kind' => TrainingProgramKind::Workshop,
                'capacity' => 36,
                'start_date' => Carbon::create($y, 5, 4),
                'end_date' => Carbon::create($y, 7, 20),
                'registration_start' => Carbon::create($y, 2, 15),
                'registration_end' => Carbon::create($y, 4, 28),
                'path_slug' => 'masar-altahil-almehnawi',
                'path_sort_order' => 2,
            ],
            [
                'title' => 'زارع',
                'slug' => 'zarih',
                'description' => 'مبادرة تربط التصميم الجرافيكي بالرسائل المجتمعية والبيئية، مع مشاريع تطبيقية تدعم التوعية الخضراء وبناء هوية بصرية مسؤولة.',
                'kind' => TrainingProgramKind::Workshop,
                'capacity' => 32,
                'start_date' => Carbon::create($y, 3, 10),
                'end_date' => Carbon::create($y, 5, 5),
                'registration_start' => Carbon::create($y, 1, 20),
                'registration_end' => Carbon::create($y, 3, 1),
                'path_slug' => 'masar-altasim-algrafiki-101',
                'path_sort_order' => 1,
            ],
            [
                'title' => 'أكفاء',
                'slug' => 'akfaa',
                'description' => 'برنامج لرفع الجاهزية المهنية عبر معايير الجودة، إدارة المخرجات، والتميز التشغيلي، بما يتماشى مع توجهات منصة كفاءات في بناء كفاءات مؤثرة في سوق العمل.',
                'kind' => TrainingProgramKind::Course,
                'capacity' => 55,
                'start_date' => Carbon::create($y, 4, 1),
                'end_date' => Carbon::create($y, 8, 30),
                'registration_start' => Carbon::create($y, 2, 1),
                'registration_end' => Carbon::create($y, 3, 20),
                'path_slug' => 'masar-muhalil-albayanat',
                'path_sort_order' => 2,
            ],
            [
                'title' => 'ملتقى تحليل البيانات',
                'slug' => 'multaqa-tahlil-albayanat',
                'description' => 'فعالية تفاعلية تجمع المهتمين بمجال تحليل البيانات، تهدف إلى تبادل الخبرات، استعراض التجارب، ومناقشة أحدث الممارسات في المجال.',
                'kind' => TrainingProgramKind::Event,
                'capacity' => 150,
                'start_date' => Carbon::create($y, 6, 12),
                'end_date' => Carbon::create($y, 6, 12),
                'registration_start' => Carbon::create($y, 4, 1),
                'registration_end' => Carbon::create($y, 6, 5),
                'path_slug' => 'masar-muhalil-albayanat',
                'path_sort_order' => 1,
            ],
            [
                'title' => 'مهارات التواصل باللغة الإنجليزية',
                'slug' => 'maharat-altawasul-billugha-alengliziyya',
                'description' => 'برنامج تدريبي يركز على التواصل الفعّال في بيئات العمل والدراسة باللغة الإنجليزية، من خلال تمارين محادثة، عروض قصيرة، وكتابة رسائل مهنية واضحة.',
                'kind' => TrainingProgramKind::Course,
                'capacity' => 40,
                'start_date' => Carbon::create($y, 3, 2),
                'end_date' => Carbon::create($y, 6, 15),
                'registration_start' => Carbon::create($y, 1, 10),
                'registration_end' => Carbon::create($y, 2, 25),
                'path_slug' => 'masar-allugha-alengliziyya',
                'path_sort_order' => 1,
            ],
        ];
    }
}
