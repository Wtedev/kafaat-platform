<?php

namespace Database\Seeders;

use App\Models\GovernanceCommittee;
use Illuminate\Database\Seeder;

class StandingCommitteesSeeder extends Seeder
{
    /** @var list{array{name: string, members: list<string>}>} */
    private const COMMITTEES = [
        [
            'name' => 'لجنة التطوير والتخطيط',
            'members' => [
                'د. إبراهيم صالح الراشد',
                'د. عماد عبدالرحمن الوشمي',
            ],
        ],
        [
            'name' => 'لجنة المتابعة والتقويم',
            'members' => [
                'د. عبدالله يوسف الضحيان',
                'د. عماد عبدالرحمن الوشمي',
            ],
        ],
        [
            'name' => 'لجنة المبادرات والمشاريع',
            'members' => [
                'د. سليمان صالح المسيطير',
                'أ. وليد فهد المرزوق',
            ],
        ],
        [
            'name' => 'لجنة تنمية الموارد المالية والاستثمار',
            'members' => [
                'أ. عبدالإله عبدالرحمن الطويان',
                'أ. عبدالعزيز إبراهيم الربدي',
            ],
        ],
    ];

    public function run(): void
    {
        $seedNames = array_column(self::COMMITTEES, 'name');

        GovernanceCommittee::query()
            ->whereNotIn('name', $seedNames)
            ->each(function (GovernanceCommittee $committee): void {
                $committee->members()->delete();
                $committee->delete();
            });

        foreach (self::COMMITTEES as $index => $committeeData) {
            $committee = GovernanceCommittee::query()->updateOrCreate(
                ['name' => $committeeData['name']],
                [
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );

            $committee->members()->whereNotIn('name', $committeeData['members'])->delete();

            foreach ($committeeData['members'] as $memberIndex => $memberName) {
                $committee->members()->updateOrCreate(
                    ['name' => $memberName],
                    [
                        'is_active' => true,
                        'sort_order' => $memberIndex + 1,
                    ],
                );
            }
        }
    }
}
