<?php

namespace Database\Seeders;

use App\Models\GovernanceDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class GeneralAssemblyMinutesSeeder extends Seeder
{
    public const CATEGORY_REGULAR = 'regular';

    public const CATEGORY_EXTRAORDINARY = 'extraordinary';

    public const CATEGORY_OTHER = 'other';

    /** @var list{array{title: string, category: string, pdf: string, document_date: ?string, sort_order: int}>} */
    private const MINUTES = [
        [
            'title' => 'محضر اجتماع الجمعية العمومية العادية — للعام المالي 2026',
            'category' => self::CATEGORY_REGULAR,
            'pdf' => 'ordinary-fiscal-2026.pdf',
            'document_date' => '2026-01-01',
            'sort_order' => 1,
        ],
        [
            'title' => 'محضر اجتماع الجمعية العمومية العادية — 2025',
            'category' => self::CATEGORY_REGULAR,
            'pdf' => 'ordinary-2025.pdf',
            'document_date' => '2025-01-01',
            'sort_order' => 2,
        ],
        [
            'title' => 'محضر اجتماع الجمعية العمومية العادية — للعام المالي 2025',
            'category' => self::CATEGORY_REGULAR,
            'pdf' => 'ordinary-fiscal-2025.pdf',
            'document_date' => '2025-01-01',
            'sort_order' => 3,
        ],
        [
            'title' => 'محضر اجتماع الجمعية العمومية العادية — 2024',
            'category' => self::CATEGORY_REGULAR,
            'pdf' => 'ordinary-2024.pdf',
            'document_date' => '2024-01-01',
            'sort_order' => 4,
        ],
        [
            'title' => 'محضر اجتماع الجمعية العمومية العادية — 2023',
            'category' => self::CATEGORY_REGULAR,
            'pdf' => 'ordinary-2023.pdf',
            'document_date' => '2023-01-01',
            'sort_order' => 5,
        ],
        [
            'title' => 'محضر اجتماع الجمعية العمومية العادية — 2022',
            'category' => self::CATEGORY_REGULAR,
            'pdf' => 'ordinary-2022.pdf',
            'document_date' => '2022-01-01',
            'sort_order' => 6,
        ],
        [
            'title' => 'محضر اجتماع الجمعية العمومية غير العادية — 2025',
            'category' => self::CATEGORY_EXTRAORDINARY,
            'pdf' => 'extraordinary-2025.pdf',
            'document_date' => '2025-01-01',
            'sort_order' => 7,
        ],
        [
            'title' => 'محضر اجتماع الجمعية العمومية غير العادية',
            'category' => self::CATEGORY_EXTRAORDINARY,
            'pdf' => 'extraordinary.pdf',
            'document_date' => null,
            'sort_order' => 8,
        ],
        [
            'title' => 'محضر اجتماع الجمعية العمومية — الاجتماع الرابع',
            'category' => self::CATEGORY_OTHER,
            'pdf' => 'fourth-meeting.pdf',
            'document_date' => null,
            'sort_order' => 9,
        ],
    ];

    public function run(): void
    {
        $seedTitles = array_column(self::MINUTES, 'title');

        GovernanceDocument::query()
            ->ofType('general_assembly_minutes')
            ->whereNotIn('title', $seedTitles)
            ->delete();

        foreach (self::MINUTES as $minute) {
            GovernanceDocument::query()->updateOrCreate(
                ['title' => $minute['title']],
                [
                    'type' => 'general_assembly_minutes',
                    'description' => $minute['category'],
                    'file_path' => $this->publishPdf($minute['pdf']),
                    'file_url' => null,
                    'document_date' => $minute['document_date'],
                    'is_active' => true,
                    'sort_order' => $minute['sort_order'],
                ],
            );
        }
    }

    private function publishPdf(string $filename): ?string
    {
        $source = database_path('seeders/assets/general-assembly-minutes/'.$filename);

        if (! File::exists($source)) {
            return null;
        }

        $relativePath = 'governance/general-assembly-minutes/'.$filename;
        $destination = storage_path('app/public/'.$relativePath);

        File::ensureDirectoryExists(dirname($destination));
        File::copy($source, $destination);

        return $relativePath;
    }
}
