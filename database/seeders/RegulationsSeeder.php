<?php

namespace Database\Seeders;

use App\Models\Regulation;
use Database\Seeders\Concerns\PublishesRegulationPdf;
use Illuminate\Database\Seeder;

class RegulationsSeeder extends Seeder
{
    use PublishesRegulationPdf;

    /** @var list<array{title: string, category: string, pdf: string, sort_order: int}>} */
    private const REGULATIONS = [
        [
            'title' => 'اللائحة الأساسية',
            'category' => 'لوائح تنظيمية',
            'pdf' => 'basic-bylaw.pdf',
            'sort_order' => 1,
        ],
        [
            'title' => 'اللائحة التنظيمية لجمعية كفاءات الأهلية',
            'category' => 'لوائح تنظيمية',
            'pdf' => 'organizational-bylaw.pdf',
            'sort_order' => 2,
        ],
        [
            'title' => 'لائحة الموارد البشرية',
            'category' => 'لوائح تنظيمية',
            'pdf' => 'hr-bylaw.pdf',
            'sort_order' => 3,
        ],
        [
            'title' => 'لائحة تنظيم العلاقة مع المستفيدين',
            'category' => 'لوائح تنظيمية',
            'pdf' => 'beneficiary-relations-bylaw.pdf',
            'sort_order' => 4,
        ],
        [
            'title' => 'سياسة الخصوصية',
            'category' => 'سياسات',
            'pdf' => 'privacy-policy.pdf',
            'sort_order' => 5,
        ],
        [
            'title' => 'سياسة إدارة المتطوعين',
            'category' => 'سياسات',
            'pdf' => 'volunteer-management-policy.pdf',
            'sort_order' => 6,
        ],
        [
            'title' => 'سياسة التطوع وإدارة المتطوعين',
            'category' => 'سياسات',
            'pdf' => 'volunteering-policy.pdf',
            'sort_order' => 7,
        ],
        [
            'title' => 'سياسة آليات الرقابة والإشراف على المنظمة',
            'category' => 'سياسات',
            'pdf' => 'oversight-policy.pdf',
            'sort_order' => 8,
        ],
        [
            'title' => 'سياسة الإبلاغ عن المخالفات وحماية مقدمي البلاغات',
            'category' => 'سياسات',
            'pdf' => 'whistleblowing-policy.pdf',
            'sort_order' => 9,
        ],
        [
            'title' => 'سياسة الاحتفاظ بالوثائق وإتلافها',
            'category' => 'سياسات',
            'pdf' => 'document-retention-policy.pdf',
            'sort_order' => 10,
        ],
        [
            'title' => 'سياسة الاشتباه بعمليات غسل الأموال وجرائم تمويل الإرهاب',
            'category' => 'سياسات',
            'pdf' => 'aml-suspicion-policy.pdf',
            'sort_order' => 11,
        ],
        [
            'title' => 'سياسة التعامل مع الشركاء المنفذين والأطراف الثالثة',
            'category' => 'سياسات',
            'pdf' => 'partners-policy.pdf',
            'sort_order' => 12,
        ],
        [
            'title' => 'سياسة تعارض المصالح',
            'category' => 'سياسات',
            'pdf' => 'conflict-of-interest-policy.pdf',
            'sort_order' => 13,
        ],
        [
            'title' => 'سياسة جمع التبرعات',
            'category' => 'سياسات',
            'pdf' => 'donations-policy.pdf',
            'sort_order' => 14,
        ],
        [
            'title' => 'سياسة قواعد السلوك',
            'category' => 'سياسات',
            'pdf' => 'code-of-conduct-policy.pdf',
            'sort_order' => 15,
        ],
        [
            'title' => 'سياسة مصفوفة الصلاحيات بين مجلس الإدارة والإدارة التنفيذية',
            'category' => 'سياسات',
            'pdf' => 'authority-matrix-policy.pdf',
            'sort_order' => 16,
        ],
        [
            'title' => 'سياسة الوقاية من عمليات غسيل الأموال وجرائم تمويل الإرهاب',
            'category' => 'سياسات',
            'pdf' => 'aml-prevention-policy.pdf',
            'sort_order' => 17,
        ],
        [
            'title' => 'الميثاق الأخلاقي للعاملين في القطاع غير الربحي',
            'category' => 'سياسات',
            'pdf' => 'ethical-charter.pdf',
            'sort_order' => 18,
        ],
        [
            'title' => 'تقييم المخاطر المتأصلة والكامنة',
            'category' => 'الامتثال والحوكمة',
            'pdf' => 'risk-assessment.pdf',
            'sort_order' => 19,
        ],
        [
            'title' => 'دليل مؤشرات وإجراءات عمليات غسل الأموال وتمويل الإرهاب',
            'category' => 'الامتثال والحوكمة',
            'pdf' => 'aml-indicators-guide.pdf',
            'sort_order' => 20,
        ],
    ];

    public function run(): void
    {
        $seedTitles = array_column(self::REGULATIONS, 'title');

        Regulation::query()
            ->whereNotIn('title', $seedTitles)
            ->delete();

        foreach (self::REGULATIONS as $regulation) {
            Regulation::query()->updateOrCreate(
                ['title' => $regulation['title']],
                [
                    'description' => null,
                    'category' => $regulation['category'],
                    'file_path' => $this->publishRegulationPdf($regulation['pdf']),
                    'file_url' => null,
                    'is_active' => true,
                    'sort_order' => $regulation['sort_order'],
                ],
            );
        }
    }
}
