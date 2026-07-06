<?php

namespace Database\Seeders;

use App\Models\GovernanceDocument;
use Database\Seeders\Concerns\PublishesGovernancePdf;
use Illuminate\Database\Seeder;

class ExecutiveReportsSeeder extends Seeder
{
    use PublishesGovernancePdf;

    public const CATEGORY_GOVERNANCE_SUPERVISION = 'governance_supervision';

    public const CATEGORY_INITIATIVES = 'initiatives';

    public const CATEGORY_OPERATIONAL = 'operational';

    public const CATEGORY_PROGRAMS = 'programs';

    public const CATEGORY_PERIODIC = 'periodic';

    /** @var list<array{title: string, category: string, pdf: string, document_date: ?string, sort_order: int}>} */
    private const REPORTS = [
        [
            'title' => 'تقرير إشراف المجلس على تنفيذ قرارات الجمعية العمومية والمراجع الخارجي — 2024',
            'category' => self::CATEGORY_GOVERNANCE_SUPERVISION,
            'pdf' => 'board-supervision-2024.pdf',
            'document_date' => '2024-12-31',
            'sort_order' => 1,
        ],
        [
            'title' => 'تقرير مبادرة حملة المخدرات — 2023',
            'category' => self::CATEGORY_INITIATIVES,
            'pdf' => 'initiative-drugs-campaign-2023.pdf',
            'document_date' => '2023-12-31',
            'sort_order' => 2,
        ],
        [
            'title' => 'تقرير مبادرة يوم التأسيس — 2023',
            'category' => self::CATEGORY_INITIATIVES,
            'pdf' => 'initiative-foundation-day-2023.pdf',
            'document_date' => '2023-12-31',
            'sort_order' => 3,
        ],
        [
            'title' => 'جدول تحقيق المؤشرات — 2023',
            'category' => self::CATEGORY_OPERATIONAL,
            'pdf' => 'indicators-achievement-2023.pdf',
            'document_date' => '2023-12-31',
            'sort_order' => 4,
        ],
        [
            'title' => 'جدول البرامج والمبادرات',
            'category' => self::CATEGORY_OPERATIONAL,
            'pdf' => 'programs-initiatives-schedule.pdf',
            'document_date' => null,
            'sort_order' => 5,
        ],
        [
            'title' => 'خطة البرامج والمؤشرات — الخطة التشغيلية',
            'category' => self::CATEGORY_OPERATIONAL,
            'pdf' => 'operational-plan-programs-indicators.pdf',
            'document_date' => null,
            'sort_order' => 6,
        ],
        [
            'title' => 'تقرير الأعمال للثلث الأول — 2022',
            'category' => self::CATEGORY_PERIODIC,
            'pdf' => 'q1-work-report-2022.pdf',
            'document_date' => '2022-04-30',
            'sort_order' => 7,
        ],
        [
            'title' => 'تقرير دورة إدارة المشاريع',
            'category' => self::CATEGORY_PROGRAMS,
            'pdf' => 'project-management-course.pdf',
            'document_date' => null,
            'sort_order' => 8,
        ],
    ];

    public function run(): void
    {
        $seedTitles = array_column(self::REPORTS, 'title');

        GovernanceDocument::query()
            ->ofType('executive_reports')
            ->whereNotIn('title', $seedTitles)
            ->delete();

        foreach (self::REPORTS as $report) {
            GovernanceDocument::query()->updateOrCreate(
                ['title' => $report['title']],
                [
                    'type' => 'executive_reports',
                    'description' => $report['category'],
                    'file_path' => $this->publishGovernancePdf('executive-reports', $report['pdf']),
                    'file_url' => null,
                    'document_date' => $report['document_date'],
                    'is_active' => true,
                    'sort_order' => $report['sort_order'],
                ],
            );
        }
    }
}
