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
            ?? User::role('admin')->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        $eman = User::query()->where('email', 'eman.almutairi@kafaat.org.sa')->first()
            ?? $creator;
        $wejdan = User::query()->where('email', 'wejdan.alsumani@kafaat.org.sa')->first()
            ?? $creator;
        $malik = User::query()->where('email', 'malik.alqasir@kafaat.org.sa')->first()
            ?? $creator;

        if ($creator === null) {
            $this->command?->warn('VolunteerOpportunitySeeder: no users found; seeding opportunities without created_by.');
        }

        $y = 2026;

        $items = [
            [
                'slug' => 'nvg-26060074414',
                'title' => 'المساهمة في تصميم الإعلانات التعريفية للقاءات الفريق التطوعي',
                'description' => implode("\n", [
                    'فرصة تطوعية تهدف إلى تصميم المواد إعلانية إبداعية للقاءات الفريق التطوعي بما يسهم في إبراز محتواها وتعزيز المشاركة فيها وفق الهوية المعتمدة.',
                    '',
                    'مجال التطوع: فني وإبداعي — تصميم الإعلانات',
                    'المهمة: المونتاج',
                    'نمط العمل: عن بُعد',
                    'رقم الفرصة على المنصة الوطنية: 26060074414',
                    'للتسجيل عبر المنصة الوطنية للعمل التطوعي:',
                    'https://nvg.gov.sa/opportunity/view-opportunity/c88e443e-f36b-1410-8b1e-00bab6d11711',
                ]),
                'capacity' => 2,
                'hours_expected' => 30.00,
                'start_date' => Carbon::create(2026, 6, 22),
                'end_date' => Carbon::create(2026, 6, 27),
                'status' => OpportunityStatus::Published,
                'assignee' => $eman,
            ],
            [
                'slug' => 'nvg-26060067032',
                'title' => 'المساهمة في تنفيذ مهام مبادرات الذكاء الاصطناعي',
                'description' => implode("\n", [
                    'فرصة تطوعية تتيح للمشاركين استكمال متطلبات المبادرة والمهام المرتبطة بها بما يسهم في تطوير المهارات والمعارف في مجال الذكاء الاصطناعي.',
                    '',
                    'مجال التطوع: تقنية المعلومات — الذكاء الاصطناعي',
                    'المهمة: إعداد التقارير',
                    'نمط العمل: عن بُعد',
                    'رقم الفرصة على المنصة الوطنية: 26060067032',
                    'للتسجيل عبر المنصة الوطنية للعمل التطوعي:',
                    'https://nvg.gov.sa/opportunity/view-opportunity/e019443e-f36b-1410-8b1e-00bab6d11711',
                ]),
                'capacity' => 50,
                'hours_expected' => 60.00,
                'start_date' => Carbon::create(2026, 6, 16),
                'end_date' => Carbon::create(2026, 6, 27),
                'status' => OpportunityStatus::Published,
                'assignee' => $eman,
            ],
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
            $slug = $data['slug'] ?? (Str::slug($data['title']) ?: 'vol-'.Str::lower(Str::random(8)));
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
                    'created_by' => $creator?->id,
                    'assigned_to' => $assignee?->id,
                ]
            );
        }
    }
}
