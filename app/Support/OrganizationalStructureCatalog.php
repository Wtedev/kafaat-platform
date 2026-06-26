<?php

namespace App\Support;

/**
 * الهيكل التنظيمي لجمعية كفاءات — مصدر ثابت للعرض العام.
 */
final class OrganizationalStructureCatalog
{
    public static function data(): array
    {
        return [
            'ceo' => [
                'title' => 'المدير التنفيذي',
                'name' => 'عبدالسلام محمد الصغير',
            ],
            'departments' => [
                [
                    'name' => 'إدارة السكرتارية',
                    'manager' => ['name' => 'عبدالحكيم عبدالرحمن الشايعي', 'title' => 'مدير الإدارة'],
                    'members' => [
                        ['name' => 'جابر مريع الشريم'],
                        ['name' => 'ريماس أحمد الصقر'],
                    ],
                ],
                [
                    'name' => 'إدارة التخطيط والتميز المؤسسي',
                    'manager' => ['name' => 'فارس محمد الحميد', 'title' => 'مدير الإدارة'],
                ],
                [
                    'name' => 'إدارة العمليات',
                    'manager' => ['name' => 'فيصل حميضان الحميضان', 'title' => 'مدير الإدارة'],
                ],
                [
                    'name' => 'إدارة التدريب والتمكين',
                    'manager' => ['name' => 'آمنة عبدالعزيز البطي', 'title' => 'مدير الإدارة'],
                ],
                [
                    'name' => 'إدارة التطوع',
                    'manager' => ['name' => 'إيمان مسعد المطيري', 'title' => 'مدير الإدارة'],
                    'team_groups' => ['الفريق التطوعي'],
                ],
                [
                    'name' => 'إدارة البرامج والأنشطة',
                    'manager' => ['name' => 'مالك صالح القصير', 'title' => 'مدير الإدارة'],
                    'sub_units' => [
                        ['name' => 'وجدان عبدالله الصمعاني', 'title' => 'إدارة المشاريع'],
                        ['name' => 'محمد زيد الزلفاوي', 'title' => 'إدارة المستفيدين'],
                    ],
                ],
                [
                    'name' => 'إدارة الهوية البصرية',
                    'manager' => ['name' => 'رهف إبراهيم البليهد', 'title' => 'مدير الإدارة'],
                    'members' => [
                        ['name' => 'نهى علي الصمعاني', 'title' => 'مصمم جرافيك'],
                        ['name' => 'لمى فؤاد المشيقح', 'title' => 'مصمم جرافيك'],
                    ],
                ],
                [
                    'name' => 'إدارة العلاقات العامة والإعلام',
                    'group_only' => true,
                    'sub_departments' => [
                        [
                            'name' => 'إدارة الاتصال المؤسسي والإعلام',
                            'manager' => ['name' => 'عبدالله عبدالرحمن السعوي', 'title' => 'مدير الإدارة'],
                        ],
                        [
                            'name' => 'إدارة العلاقات العامة والشركات',
                            'manager' => ['name' => 'حسام عبدالعزيز التويجري', 'title' => 'مدير الإدارة'],
                            'members' => [
                                ['name' => 'زياد عبدالله الحصيان'],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'إدارة تنمية الموارد المالية',
                    'manager' => ['name' => 'بسام حمد الخضيري', 'title' => 'مدير الإدارة'],
                ],
            ],
        ];
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return '؟';
        }

        $first = mb_substr($parts[0], 0, 1);
        $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';

        return $first.$last;
    }
}
