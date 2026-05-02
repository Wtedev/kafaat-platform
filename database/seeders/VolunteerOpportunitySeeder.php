<?php

namespace Database\Seeders;

use App\Enums\OpportunityStatus;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VolunteerOpportunitySeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::query()->where('email', 'lama.almeshiqeh@kafaat.org.sa')->first()
            ?? User::role('admin')->orderBy('id')->first();

        $volunteeringManager = User::query()->where('email', 'eman.almutairi@kafaat.org.sa')->first();

        if ($creator === null) {
            $this->command?->error('VolunteerOpportunitySeeder: missing admin. Run UserSeeder first.');

            return;
        }

        $y = (int) now()->year;

        $items = [
            [
                'title' => 'محلل بيانات تطوعي لدعم المبادرات',
                'description' => 'دعم فرق المبادرات في جمع البيانات، تنظيمها، وإعداد ملخصات بسيطة تساعد على اتخاذ القرار، مع الالتزام بخصوصية البيانات وأخلاقيات الاستخدام.',
                'capacity' => 8,
                'hours_expected' => 40.00,
                'start_date' => Carbon::create($y, 3, 1),
                'end_date' => Carbon::create($y, 9, 30),
            ],
            [
                'title' => 'منسق فعاليات ومؤتمرات',
                'description' => 'تنسيق الجداول الزمنية، التواصل مع المتحدثين، إدارة التسجيل في الموقع، ومتابعة تجربة المشاركين قبل وبعد الفعالية.',
                'capacity' => 12,
                'hours_expected' => 60.00,
                'start_date' => Carbon::create($y, 4, 15),
                'end_date' => Carbon::create($y, 11, 15),
            ],
            [
                'title' => 'أخصائي استهداف وتسويق برامج',
                'description' => 'صياغة رسائل استهداف مناسبة للفئات المختلفة، دعم الحملات الرقمية، وقياس مؤشرات الاستجابة بالتنسيق مع فريق المحتوى.',
                'capacity' => 6,
                'hours_expected' => 35.00,
                'start_date' => Carbon::create($y, 2, 10),
                'end_date' => Carbon::create($y, 8, 31),
            ],
            [
                'title' => 'منسق مقابلات وتوظيف',
                'description' => 'تنظيم جداول المقابلات، التواصل مع المرشحين، وأرشفة النتائج وفق سياسات الخصوصية المعتمدة لدى المنصة.',
                'capacity' => 5,
                'hours_expected' => 28.00,
                'start_date' => Carbon::create($y, 5, 1),
                'end_date' => Carbon::create($y, 10, 31),
            ],
            [
                'title' => 'مدرب لإقامة ورش تدريبية',
                'description' => 'إعداد وتقديم ورش عملية قصيرة ضمن برامج المنصة، مع أدلة تنفيذ وتمارين مشاركة للحضور.',
                'capacity' => 10,
                'hours_expected' => 48.00,
                'start_date' => Carbon::create($y, 3, 20),
                'end_date' => Carbon::create($y, 12, 15),
            ],
            [
                'title' => 'متطوع للمشاركة في مبادرات التشجير',
                'description' => 'المساهمة في تنظيم مواقع الزراعة، التوعية للمتطوعين، ومتابعة سلامة المشاركين وفق تعليمات الجهات الشريكة.',
                'capacity' => 25,
                'hours_expected' => 20.00,
                'start_date' => Carbon::create($y, 11, 1),
                'end_date' => Carbon::create($y, 12, 20),
            ],
        ];

        foreach ($items as $data) {
            $slug = Str::slug($data['title']) ?: 'vol-'.Str::random(8);

            VolunteerOpportunity::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'capacity' => $data['capacity'],
                    'hours_expected' => $data['hours_expected'],
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'status' => OpportunityStatus::Published,
                    'published_at' => now()->subDays(5),
                    'created_by' => $creator->id,
                    'assigned_to' => $volunteeringManager?->id,
                ]
            );
        }
    }
}
