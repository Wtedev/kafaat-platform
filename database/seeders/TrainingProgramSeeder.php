<?php

namespace Database\Seeders;

use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
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
