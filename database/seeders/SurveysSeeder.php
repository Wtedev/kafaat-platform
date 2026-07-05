<?php

namespace Database\Seeders;

use App\Models\GovernanceDocument;
use Database\Seeders\Concerns\PublishesGovernancePdf;
use Illuminate\Database\Seeder;

class SurveysSeeder extends Seeder
{
    use PublishesGovernancePdf;

    public const CATEGORY_GENERAL = 'general';

    public const CATEGORY_BENEFICIARY_SATISFACTION = 'beneficiary_satisfaction';

    /** @var list{array{title: string, category: string, pdf: string, sort_order: int}>} */
    private const SURVEYS = [
        [
            'title' => 'جمعية كفاءات الأهلية لبناء قدرات الشباب بمنطقة القصيم',
            'category' => self::CATEGORY_GENERAL,
            'pdf' => 'association-profile.pdf',
            'sort_order' => 1,
        ],
        [
            'title' => 'قياس رضا المستفيدين — برنامج كفاءات شبابية',
            'category' => self::CATEGORY_BENEFICIARY_SATISFACTION,
            'pdf' => 'kafaat-shababiya.pdf',
            'sort_order' => 2,
        ],
        [
            'title' => 'قياس رضا المستفيدين — برنامج مسرعات القيادة',
            'category' => self::CATEGORY_BENEFICIARY_SATISFACTION,
            'pdf' => 'leadership-accelerators.pdf',
            'sort_order' => 3,
        ],
        [
            'title' => 'قياس رضا المستفيدين — برنامج مدرك',
            'category' => self::CATEGORY_BENEFICIARY_SATISFACTION,
            'pdf' => 'mudrak.pdf',
            'sort_order' => 4,
        ],
        [
            'title' => 'قياس رضا المستفيدين — برنامج تأهيل العاملين مع الشباب',
            'category' => self::CATEGORY_BENEFICIARY_SATISFACTION,
            'pdf' => 'youth-workers-qualification.pdf',
            'sort_order' => 5,
        ],
        [
            'title' => 'قياس رضا المستفيدين — برنامج رائد',
            'category' => self::CATEGORY_BENEFICIARY_SATISFACTION,
            'pdf' => 'raed-program.pdf',
            'sort_order' => 6,
        ],
    ];

    public function run(): void
    {
        $seedTitles = array_column(self::SURVEYS, 'title');

        GovernanceDocument::query()
            ->ofType('surveys')
            ->whereNotIn('title', $seedTitles)
            ->delete();

        foreach (self::SURVEYS as $survey) {
            GovernanceDocument::query()->updateOrCreate(
                ['title' => $survey['title']],
                [
                    'type' => 'surveys',
                    'description' => $survey['category'],
                    'file_path' => $this->publishGovernancePdf('surveys', $survey['pdf']),
                    'file_url' => null,
                    'document_date' => null,
                    'is_active' => true,
                    'sort_order' => $survey['sort_order'],
                ],
            );
        }
    }
}
