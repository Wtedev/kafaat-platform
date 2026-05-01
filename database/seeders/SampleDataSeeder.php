<?php

namespace Database\Seeders;

use App\Enums\CourseStatus;
use App\Enums\OpportunityStatus;
use App\Enums\PathStatus;
use App\Enums\ProgramStatus;
use App\Models\LearningPath;
use App\Models\PathCourse;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();

        $this->seedLearningPaths($admin);
        $this->seedTrainingPrograms($admin);
        $this->seedVolunteerOpportunities($admin);
    }

    // ─── Learning paths ───────────────────────────────────────────────────────

    private function seedLearningPaths(User $admin): void
    {
        $paths = [
            [
                'title' => 'مسار ريادة الأعمال',
                'description' => 'مسار متكامل يُعدّك لبناء مشروعك الخاص، من الفكرة حتى التنفيذ. يشمل التخطيط المالي، دراسة الجدوى، والتسويق الرقمي.',
                'capacity' => 30,
                'courses' => [
                    'مقدمة في ريادة الأعمال',
                    'التخطيط المالي للمشاريع الناشئة',
                    'التسويق الرقمي وبناء العلامة التجارية',
                ],
            ],
            [
                'title' => 'مسار تطوير المهارات القيادية',
                'description' => 'برنامج تدريبي مكثف لتطوير مهارات القيادة والإدارة، يُخصَّص للشباب الطموح الراغب في صناعة الفارق في بيئة عمله.',
                'capacity' => 25,
                'courses' => [
                    'مبادئ القيادة الفعّالة',
                    'إدارة الفرق والتحفيز',
                    'صنع القرار وحل الإشكاليات',
                ],
            ],
            [
                'title' => 'مسار المهارات الرقمية',
                'description' => 'مسار شامل يُغطّي أساسيات البرمجة، تحليل البيانات، والذكاء الاصطناعي لتأهيلك لسوق العمل الرقمي.',
                'capacity' => null,
                'courses' => [
                    'أساسيات البرمجة بـ Python',
                    'تحليل البيانات والتصور البياني',
                    'مقدمة إلى الذكاء الاصطناعي',
                ],
            ],
        ];

        foreach ($paths as $data) {
            $slug = Str::slug($data['title']) ?: 'path-'.Str::random(6);

            $path = LearningPath::firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'capacity' => $data['capacity'],
                    'status' => PathStatus::Published,
                    'published_at' => now()->subDays(rand(5, 30)),
                    'created_by' => $admin->id,
                ]
            );

            foreach ($data['courses'] as $i => $courseTitle) {
                PathCourse::firstOrCreate(
                    ['learning_path_id' => $path->id, 'title' => $courseTitle],
                    [
                        'description' => 'محتوى تفصيلي لـ '.$courseTitle.' ضمن '.$data['title'].'.',
                        'sort_order' => $i + 1,
                        'status' => CourseStatus::Published,
                        'published_at' => now()->subDays(rand(3, 20)),
                    ]
                );
            }
        }
    }

    // ─── Training programs ────────────────────────────────────────────────────

    private function seedTrainingPrograms(User $admin): void
    {
        $programs = [
            [
                'title' => 'برنامج إعداد المدرّب المعتمد',
                'description' => 'برنامج متخصص معتمد لتأهيل المدرّبين المحترفين. يشمل تقنيات التدريب، تصميم المحتوى، وإدارة الفصل التدريبي.',
                'capacity' => 20,
                'registration_start' => now()->subDays(10),
                'registration_end' => now()->addDays(20),
                'start_date' => now()->addDays(25),
                'end_date' => now()->addDays(55),
            ],
            [
                'title' => 'برنامج المهارات الوظيفية',
                'description' => 'برنامج عملي لتطوير مهارات السيرة الذاتية، المقابلات الشخصية، والعمل الجماعي لمساعدة الباحثين عن عمل.',
                'capacity' => 35,
                'registration_start' => now()->subDays(5),
                'registration_end' => now()->addDays(15),
                'start_date' => now()->addDays(18),
                'end_date' => now()->addDays(32),
            ],
            [
                'title' => 'برنامج الصحة النفسية في بيئة العمل',
                'description' => 'برنامج تعريفي بمفاهيم الصحة النفسية وإدارة ضغوط العمل، مناسب للموظفين والمدراء على حدٍّ سواء.',
                'capacity' => null,
                'registration_start' => now()->subDays(15),
                'registration_end' => now()->addDays(5),
                'start_date' => now()->addDays(10),
                'end_date' => now()->addDays(12),
            ],
        ];

        foreach ($programs as $data) {
            $slug = Str::slug($data['title']) ?: 'program-'.Str::random(6);

            TrainingProgram::firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'capacity' => $data['capacity'],
                    'registration_start' => $data['registration_start'],
                    'registration_end' => $data['registration_end'],
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'status' => ProgramStatus::Published,
                    'published_at' => now()->subDays(rand(5, 20)),
                    'created_by' => $admin->id,
                ]
            );
        }
    }

    // ─── Volunteer opportunities ──────────────────────────────────────────────

    private function seedVolunteerOpportunities(User $admin): void
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
                ]
            );
        }
    }
}
