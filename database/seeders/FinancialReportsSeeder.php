<?php

namespace Database\Seeders;

use App\Models\GovernanceDocument;
use Database\Seeders\Concerns\PublishesGovernancePdf;
use Illuminate\Database\Seeder;

class FinancialReportsSeeder extends Seeder
{
    use PublishesGovernancePdf;

    public const CATEGORY_FINANCIAL_STATEMENTS = 'financial_statements';

    public const CATEGORY_APPROVED_BUDGETS = 'approved_budgets';

    public const CATEGORY_ESTIMATED_BUDGETS = 'estimated_budgets';

    public const CATEGORY_ANNUAL_REPORTS = 'annual_reports';

    /** @var list<array{title: string, category: string, pdf: string, document_date: ?string, sort_order: int}>} */
    private const REPORTS = [
        [
            'title' => 'القوائم المالية — 2025',
            'category' => self::CATEGORY_FINANCIAL_STATEMENTS,
            'pdf' => 'financial-statements-2025.pdf',
            'document_date' => '2025-12-31',
            'sort_order' => 1,
        ],
        [
            'title' => 'القوائم المالية — 2024',
            'category' => self::CATEGORY_FINANCIAL_STATEMENTS,
            'pdf' => 'financial-statements-2024.pdf',
            'document_date' => '2024-12-31',
            'sort_order' => 2,
        ],
        [
            'title' => 'القوائم المالية — 2023',
            'category' => self::CATEGORY_FINANCIAL_STATEMENTS,
            'pdf' => 'financial-statements-2023.pdf',
            'document_date' => '2023-12-31',
            'sort_order' => 3,
        ],
        [
            'title' => 'القوائم المالية — 2022',
            'category' => self::CATEGORY_FINANCIAL_STATEMENTS,
            'pdf' => 'financial-statements-2022.pdf',
            'document_date' => '2022-12-31',
            'sort_order' => 4,
        ],
        [
            'title' => 'الميزانية المعتمدة — 2021',
            'category' => self::CATEGORY_APPROVED_BUDGETS,
            'pdf' => 'approved-budget-2021.pdf',
            'document_date' => '2021-12-31',
            'sort_order' => 5,
        ],
        [
            'title' => 'الميزانية المعتمدة — 2020',
            'category' => self::CATEGORY_APPROVED_BUDGETS,
            'pdf' => 'approved-budget-2020.pdf',
            'document_date' => '2020-12-31',
            'sort_order' => 6,
        ],
        [
            'title' => 'الميزانية المعتمدة — 2019',
            'category' => self::CATEGORY_APPROVED_BUDGETS,
            'pdf' => 'approved-budget-2019.pdf',
            'document_date' => '2019-12-31',
            'sort_order' => 7,
        ],
        [
            'title' => 'الميزانية المعتمدة — 2018',
            'category' => self::CATEGORY_APPROVED_BUDGETS,
            'pdf' => 'approved-budget-2018.pdf',
            'document_date' => '2018-12-31',
            'sort_order' => 8,
        ],
        [
            'title' => 'الموازنة التقديرية لعام 2026',
            'category' => self::CATEGORY_ESTIMATED_BUDGETS,
            'pdf' => 'estimated-budget-2026.pdf',
            'document_date' => '2026-01-01',
            'sort_order' => 9,
        ],
        [
            'title' => 'الموازنة التقديرية — 2022',
            'category' => self::CATEGORY_ESTIMATED_BUDGETS,
            'pdf' => 'estimated-budget-2022.pdf',
            'document_date' => '2022-01-01',
            'sort_order' => 10,
        ],
        [
            'title' => 'التقرير المالي السنوي — 2021',
            'category' => self::CATEGORY_ANNUAL_REPORTS,
            'pdf' => 'annual-financial-report-2021.pdf',
            'document_date' => '2021-12-31',
            'sort_order' => 11,
        ],
    ];

    public function run(): void
    {
        $seedTitles = array_column(self::REPORTS, 'title');

        GovernanceDocument::query()
            ->ofType('financial_reports')
            ->whereNotIn('title', $seedTitles)
            ->delete();

        foreach (self::REPORTS as $report) {
            GovernanceDocument::query()->updateOrCreate(
                ['title' => $report['title']],
                [
                    'type' => 'financial_reports',
                    'description' => $report['category'],
                    'file_path' => $this->publishGovernancePdf('financial-reports', $report['pdf']),
                    'file_url' => null,
                    'document_date' => $report['document_date'],
                    'is_active' => true,
                    'sort_order' => $report['sort_order'],
                ],
            );
        }
    }
}
