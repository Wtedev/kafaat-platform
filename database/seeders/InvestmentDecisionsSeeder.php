<?php

namespace Database\Seeders;

use App\Models\InvestmentDecisionYear;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class InvestmentDecisionsSeeder extends Seeder
{
    /** @var list{array{year: int, items: list<string>, empty_message: ?string, pdf: ?string}>} */
    private const YEARS = [
        [
            'year' => 2024,
            'items' => [],
            'empty_message' => 'لا يوجد قرارات استثمارية لعام 2024م',
            'pdf' => null,
        ],
        [
            'year' => 2023,
            'items' => [
                'الموافقة على التسجيل في منصة إحسان للعمل الخيري، وعرض المبادرات والأعمال التشغيلية تعزيزاً للموارد المالية للجمعية.',
                'الموافقة على التقديم في خدمة دعم البرامج والمشاريع التابعة لصندوق دعم الجمعيات، مع التزام الجمعية بتوفير النسبة المساهمة الذاتية والتي لا تقل عن 20% من تكلفة المشروع.',
                'بناء على توصية لجنة تنمية الموارد المالية، جرى الموافقة على المشاركة في الصندوق الاستثماري الوقفي لمجلس وجمعيات منطقة القصيم.',
                'بناء على توصية لجنة الموارد المالية، الموافقة على المشاركة في الصناديق الاستثمارية الأخرى لتنمية موارد الجمعية، بعد دراسة إمكانية ذلك مع المركز الوطني لتنمية القطاع غير الربحي.',
            ],
            'empty_message' => null,
            'pdf' => '2023.pdf',
        ],
        [
            'year' => 2022,
            'items' => [
                'تم الاطلاع واعتماد لائحة وسياسة الاستثمار.',
            ],
            'empty_message' => null,
            'pdf' => '2022.pdf',
        ],
        [
            'year' => 2021,
            'items' => [
                'تم تكليف الأستاذ/ عبدالعزيز الربدي لمتابعة مستجدات المقر الاستثماري الخاص بالجمعية.',
                'الموافقة على المبنى الاستثماري المقترح الواقع بمدينة بريدة – الدائري الشمالي غرب قصر الرصافة للاحتفالات.',
                'الموافقة على المبنى الاستثماري المقترح الواقع بمدينة بريدة – الدائري الشمالي غرب قصر الرصافة للاحتفالات.',
            ],
            'empty_message' => null,
            'pdf' => '2021.pdf',
        ],
    ];

    public function run(): void
    {
        $seedYears = array_column(self::YEARS, 'year');

        InvestmentDecisionYear::query()
            ->whereNotIn('year', $seedYears)
            ->each(function (InvestmentDecisionYear $year): void {
                $year->items()->delete();
                $year->delete();
            });

        foreach (self::YEARS as $index => $yearData) {
            $filePath = $yearData['pdf']
                ? $this->publishPdf($yearData['pdf'])
                : null;

            $year = InvestmentDecisionYear::query()->updateOrCreate(
                ['year' => $yearData['year']],
                [
                    'title' => 'قرارات الاستثمار لعام '.$yearData['year'],
                    'file_path' => $filePath,
                    'empty_message' => $yearData['empty_message'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );

            $year->items()->whereNotIn('sort_order', range(1, count($yearData['items'])))->delete();

            foreach ($yearData['items'] as $itemIndex => $content) {
                $year->items()->updateOrCreate(
                    ['sort_order' => $itemIndex + 1],
                    [
                        'content' => $content,
                        'is_active' => true,
                    ],
                );
            }
        }
    }

    private function publishPdf(string $filename): ?string
    {
        $source = database_path('seeders/assets/investment-decisions/'.$filename);

        if (! File::exists($source)) {
            return null;
        }

        $relativePath = 'governance/investment-decisions/'.$filename;
        $destination = storage_path('app/public/'.$relativePath);

        File::ensureDirectoryExists(dirname($destination));
        File::copy($source, $destination);

        return $relativePath;
    }
}
