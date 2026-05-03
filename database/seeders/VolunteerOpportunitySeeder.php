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
        $creator = User::query()->where('email', 'lama@kafaat.org.sa')->first()
            ?? User::role('admin')->orderBy('id')->first();

        $eman = User::query()->where('email', 'eman.almutairi@kafaat.org.sa')->first();
        $wejdan = User::query()->where('email', 'wejdan.alsumani@kafaat.org.sa')->first();
        $malik = User::query()->where('email', 'malik.alqasir@kafaat.org.sa')->first();

        if ($creator === null) {
            $this->command?->error('VolunteerOpportunitySeeder: missing admin. Run AdminUserSeeder first.');

            return;
        }

        $y = 2026;

        $items = [
            [
                'title' => 'محلل بيانات تطوعي لدعم المبادرات',
                'description' => 'دعم فرق المبادرات في جمع البيانات، تنظيمها، وإعداد ملخصات بسيطة تساعد على اتخاذ القرار، مع الالتزام بخصوصية البيانات وأخلاقيات الاستخدام.',
                'capacity' => 10,
                'hours_expected' => 40.00,
                'start_date' => Carbon::create($y, 3, 1),
                'end_date' => Carbon::create($y, 9, 30),
                'status' => OpportunityStatus::Published,
                'assignee' => $eman,
            ],
            [
                'title' => 'منسق فعاليات ومؤتمرات',
                'description' => 'تنسيق الجداول الزمنية، التواصل مع المتحدثين، إدارة التسجيل في الموقع، ومتابعة تجربة المشاركين قبل وبعد الفعالية.',
                'capacity' => 14,
                'hours_expected' => 60.00,
                'start_date' => Carbon::create($y, 4, 15),
                'end_date' => Carbon::create($y, 11, 15),
                'status' => OpportunityStatus::Published,
                'assignee' => $wejdan,
            ],
            [
                'title' => 'أخصائي استهداف وتسويق برامج',
                'description' => 'صياغة رسائل استهداف مناسبة للفئات المختلفة، دعم الحملات الرقمية، وقياس مؤشرات الاستجابة بالتنسيق مع فريق المحتوى.',
                'capacity' => 8,
                'hours_expected' => 35.00,
                'start_date' => Carbon::create($y, 2, 10),
                'end_date' => Carbon::create($y, 8, 31),
                'status' => OpportunityStatus::Draft,
                'assignee' => $malik,
            ],
            [
                'title' => 'منسق مقابلات وتوظيف',
                'description' => 'تنظيم جداول المقابلات، التواصل مع المرشحين، وأرشفة النتائج وفق سياسات الخصوصية المعتمدة لدى المنصة.',
                'capacity' => 6,
                'hours_expected' => 28.00,
                'start_date' => Carbon::create($y, 5, 1),
                'end_date' => Carbon::create($y, 10, 31),
                'status' => OpportunityStatus::Published,
                'assignee' => $eman,
            ],
            [
                'title' => 'مدرب لإقامة ورش تدريبية',
                'description' => 'إعداد وتقديم ورش عملية قصيرة ضمن برامج المنصة، مع أدلة تنفيذ وتمارين مشاركة للحضور.',
                'capacity' => 12,
                'hours_expected' => 48.00,
                'start_date' => Carbon::create($y, 3, 20),
                'end_date' => Carbon::create($y, 12, 15),
                'status' => OpportunityStatus::Published,
                'assignee' => $malik,
            ],
            [
                'title' => 'متطوع للمشاركة في مبادرات التشجير',
                'description' => 'المساهمة في تنظيم مواقع الزراعة، التوعية للمتطوعين، ومتابعة سلامة المشاركين وفق تعليمات الجهات الشريكة.',
                'capacity' => 28,
                'hours_expected' => 20.00,
                'start_date' => Carbon::create($y, 11, 1),
                'end_date' => Carbon::create($y, 12, 20),
                'status' => OpportunityStatus::Archived,
                'assignee' => $wejdan,
            ],
            [
                'title' => 'دعم إنتاج محتوى تعليمي قصير',
                'description' => 'المساعدة في تحضير مواد بصرية بسيطة، مراجعة النصوص التعريفية، وتنسيق الجداول الزمنية لنشر المحتوى.',
                'capacity' => 9,
                'hours_expected' => 24.00,
                'start_date' => Carbon::create($y, 6, 1),
                'end_date' => Carbon::create($y, 10, 30),
                'status' => OpportunityStatus::Published,
                'assignee' => $eman,
            ],
            [
                'title' => 'متابعة تجربة المستفيدين في الفعاليات',
                'description' => 'جمع انطباعات الحضور بعد الفعاليات، تلخيص الملاحظات، ورفع تقارير مختصرة لفريق الجودة.',
                'capacity' => 7,
                'hours_expected' => 18.00,
                'start_date' => Carbon::create($y, 4, 5),
                'end_date' => Carbon::create($y, 9, 5),
                'status' => OpportunityStatus::Draft,
                'assignee' => $malik,
            ],
        ];

        foreach ($items as $data) {
            $slug = Str::slug($data['title']) ?: 'vol-'.Str::lower(Str::random(8));
            $assignee = $data['assignee'] ?? $eman;

            VolunteerOpportunity::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'capacity' => $data['capacity'],
                    'hours_expected' => $data['hours_expected'],
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'status' => $data['status'],
                    'published_at' => $data['status'] === OpportunityStatus::Published
                        ? now()->subDays(4)
                        : null,
                    'created_by' => $creator->id,
                    'assigned_to' => $assignee?->id,
                ]
            );
        }
    }
}
