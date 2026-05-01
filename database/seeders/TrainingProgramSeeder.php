<?php

namespace Database\Seeders;

use App\Enums\ProgramStatus;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TrainingProgramSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first()
            ?? User::where('email', 'admin@kafaat.test')->first();

        $programs = [
            [
                'title'              => 'سند',
                'description'        => 'تعزيز كفاءة المورد البشري من خلال برنامج تدريبي متكامل يُطوّر المهارات المهنية والقدرات الوظيفية للمستفيدين.',
                'capacity'           => 30,
                'start_date'         => Carbon::parse('2026-07-01'),
                'end_date'           => Carbon::parse('2026-08-30'),
                'registration_start' => Carbon::parse('2026-05-15'),
                'registration_end'   => Carbon::parse('2026-06-25'),
            ],
            [
                'title'              => 'زارع',
                'description'        => 'بناء الكفاءات في القطاع الزراعي وتطوير مهارات المشاركين في مجال الزراعة المستدامة وإدارة الموارد الطبيعية.',
                'capacity'           => 25,
                'start_date'         => Carbon::parse('2026-07-15'),
                'end_date'           => Carbon::parse('2026-09-15'),
                'registration_start' => Carbon::parse('2026-05-20'),
                'registration_end'   => Carbon::parse('2026-07-05'),
            ],
            [
                'title'              => 'قادة',
                'description'        => 'تطوير القيادات الشبابية وصقل مهارات الإدارة والقيادة الفعّالة لتأهيل جيل قادر على قيادة التغيير.',
                'capacity'           => 20,
                'start_date'         => Carbon::parse('2026-08-01'),
                'end_date'           => Carbon::parse('2026-09-30'),
                'registration_start' => Carbon::parse('2026-06-01'),
                'registration_end'   => Carbon::parse('2026-07-20'),
            ],
            [
                'title'              => 'ساعد',
                'description'        => 'تمكين مدراء المشاريع من أدوات ومنهجيات إدارة المشاريع الاحترافية لتحقيق نتائج قابلة للقياس والاستدامة.',
                'capacity'           => 25,
                'start_date'         => Carbon::parse('2026-08-15'),
                'end_date'           => Carbon::parse('2026-10-15'),
                'registration_start' => Carbon::parse('2026-06-10'),
                'registration_end'   => Carbon::parse('2026-08-05'),
            ],
            [
                'title'              => 'أكفاء',
                'description'        => 'العلاقات العامة والاتصال المؤسسي: برنامج احترافي لبناء مهارات التواصل وإدارة الصورة المؤسسية.',
                'capacity'           => 30,
                'start_date'         => Carbon::parse('2026-09-01'),
                'end_date'           => Carbon::parse('2026-10-31'),
                'registration_start' => Carbon::parse('2026-07-01'),
                'registration_end'   => Carbon::parse('2026-08-20'),
            ],
            [
                'title'              => 'ملتقى تحليل البيانات',
                'description'        => 'بناء مهارات تحليل البيانات والاستفادة منها في دعم القرار المؤسسي باستخدام أدوات تحليل حديثة.',
                'capacity'           => null,
                'start_date'         => Carbon::parse('2026-09-15'),
                'end_date'           => Carbon::parse('2026-11-15'),
                'registration_start' => Carbon::parse('2026-07-15'),
                'registration_end'   => Carbon::parse('2026-09-05'),
            ],
        ];

        foreach ($programs as $data) {
            $slug = Str::slug($data['title']);

            // Ensure slug uniqueness if arabic slug is empty
            if (empty($slug)) {
                $slug = 'program-' . Str::random(6);
            }

            TrainingProgram::firstOrCreate(
                ['slug' => $slug],
                [
                    'title'              => $data['title'],
                    'description'        => $data['description'],
                    'capacity'           => $data['capacity'],
                    'start_date'         => $data['start_date'],
                    'end_date'           => $data['end_date'],
                    'registration_start' => $data['registration_start'],
                    'registration_end'   => $data['registration_end'],
                    'status'             => ProgramStatus::Published,
                    'published_at'       => now(),
                    'created_by'         => $admin?->id,
                ]
            );
        }
    }
}
