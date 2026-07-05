<?php

namespace Database\Seeders;

use App\Models\BoardMember;
use Illuminate\Database\Seeder;

class BoardMembersSeeder extends Seeder
{
    /** @var list{array{name: string, role: string}>} */
    private const MEMBERS = [
        ['name' => 'د. عبد الله بن يوسف الضحيان', 'role' => 'رئيس المجلس'],
        ['name' => 'د. عماد بن عبد الرحمن الوشمي', 'role' => 'نائب الرئيس'],
        ['name' => 'أ. عبد الإله بن عبد الرحمن الطويان', 'role' => 'المشرف المالي'],
        ['name' => 'أ. عبد العزيز بن إبراهيم الربدي', 'role' => 'عضو المجلس'],
        ['name' => 'أ. إبراهيم بن صالح الراشد', 'role' => 'عضو المجلس'],
        ['name' => 'د. سليمان بن صالح المسيطير', 'role' => 'عضو المجلس'],
        ['name' => 'د. وليد بن فهد المرزوق', 'role' => 'عضو المجلس'],
    ];

    public function run(): void
    {
        BoardMember::query()
            ->where('group', BoardMember::GROUP_BOARD)
            ->whereNotIn('name', array_column(self::MEMBERS, 'name'))
            ->delete();

        foreach (self::MEMBERS as $index => $member) {
            BoardMember::query()->updateOrCreate(
                [
                    'group' => BoardMember::GROUP_BOARD,
                    'name' => $member['name'],
                ],
                [
                    'role' => $member['role'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
