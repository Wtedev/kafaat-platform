<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * الهيكل التنظيمي لجمعية كفاءات — مصدر ثابت للعرض العام.
 */
final class OrganizationalStructureCatalog
{
    public static function data(): array
    {
        $data = [
            'ceo' => [
                'title' => 'مدير تنفيذي',
                'name' => 'عبدالسلام محمد الصغير',
            ],
            'departments' => [
                [
                    'name' => 'إدارة السكرتارية',
                    'manager' => ['name' => 'عبدالحكيم عبدالرحمن الشايعي', 'title' => 'سكرتير إداري'],
                    'members' => [
                        ['name' => 'جابر مريع الشريم', 'title' => 'مساعد إداري'],
                    ],
                ],
                [
                    'name' => 'إدارة الموارد البشرية',
                    'members' => [
                        [
                            'name' => 'ريماس أحمد الصقر',
                            'title' => 'متدرب',
                            'accent' => 'sanad',
                        ],
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
                    'sub_departments' => [
                        [
                            'name' => 'قسم المشاريع',
                            'manager' => ['name' => 'وجدان عبدالله الصمعاني', 'title' => 'رئيس قسم'],
                        ],
                        [
                            'name' => 'قسم المستفيدين',
                            'manager' => ['name' => 'محمد زيد الزلفاوي', 'title' => 'رئيس قسم'],
                        ],
                    ],
                ],
                [
                    'name' => 'إدارة الهوية البصرية',
                    'manager' => ['name' => 'رهف إبراهيم البليهد', 'title' => 'مدير الإدارة'],
                    'members' => [
                        ['name' => 'نهى علي الصمعاني', 'title' => 'أخصائي تصميم جرافيك أول'],
                        ['name' => 'لمى فؤاد المشيقح', 'title' => 'مصمم جرافيك'],
                    ],
                ],
                [
                    'name' => 'إدارة العلاقات العامة والإعلام',
                    'group_only' => true,
                    'sub_departments' => [
                        [
                            'name' => 'قسم الاتصال المؤسسي',
                            'manager' => ['name' => 'عبدالله عبدالرحمن السعوي', 'title' => 'رئيس قسم'],
                        ],
                        [
                            'name' => 'قسم العلاقات العامة والشراكات',
                            'manager' => ['name' => 'حسام عبدالعزيز التويجري', 'title' => 'رئيس قسم'],
                            'members' => [
                                ['name' => 'زياد عبدالله الحصيان', 'title' => 'عضو في قسم العلاقات العامة والشراكات'],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'الإدارة المالية',
                    'manager' => ['name' => 'بسام حمد الخضيري', 'title' => 'مدير الإدارة'],
                ],
            ],
        ];

        return self::withResolvedPhotos(self::withResolvedAccents($data));
    }

    /**
     * Avatar circle accents by role:
     * - مدراء الإدارات / المدير التنفيذي → primary (brand blue)
     * - الجميع غير مدراء الإدارات (بما فيهم رؤساء الأقسام) → gray
     * - الفريق التطوعي → yellow
     * - متدربة سند (ريماس) → sanad (purple), kept when set explicitly
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function withResolvedAccents(array $data): array
    {
        $apply = static function (?array $person, string $role) {
            if ($person === null || ! isset($person['name'])) {
                return $person;
            }

            // Explicit accents (e.g. Remas → sanad) win.
            if (isset($person['accent']) && is_string($person['accent']) && trim($person['accent']) !== '') {
                return $person;
            }

            $title = (string) ($person['title'] ?? '');
            $isDeptManager = $role === 'ceo'
                || $role === 'dept_manager'
                || str_contains($title, 'مدير الإدارة')
                || str_contains($title, 'مدير تنفيذي');

            $person['accent'] = $isDeptManager ? 'primary' : 'gray';

            return $person;
        };

        $data['ceo'] = $apply($data['ceo'] ?? null, 'ceo') ?? $data['ceo'];

        foreach ($data['departments'] as &$dept) {
            if (isset($dept['manager'])) {
                $dept['manager'] = $apply($dept['manager'], 'dept_manager');
            }

            foreach ($dept['members'] ?? [] as $i => $member) {
                $dept['members'][$i] = $apply($member, 'staff');
            }

            foreach ($dept['team_groups'] ?? [] as $ti => $team) {
                if (is_string($team)) {
                    $dept['team_groups'][$ti] = [
                        'name' => $team,
                        'accent' => 'yellow',
                    ];

                    continue;
                }

                if (! is_array($team)) {
                    continue;
                }

                if (! isset($team['accent']) || ! is_string($team['accent']) || trim($team['accent']) === '') {
                    $team['accent'] = 'yellow';
                }

                $dept['team_groups'][$ti] = $team;
            }

            foreach ($dept['sub_departments'] ?? [] as $si => $sub) {
                if (isset($sub['manager'])) {
                    $dept['sub_departments'][$si]['manager'] = $apply($sub['manager'], 'section_head');
                }

                foreach ($sub['members'] ?? [] as $mi => $member) {
                    $dept['sub_departments'][$si]['members'][$mi] = $apply($member, 'staff');
                }
            }
        }
        unset($dept);

        return $data;
    }

    /**
     * Attach public photo URLs from staff profiles when names match.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function withResolvedPhotos(array $data): array
    {
        $photosByName = self::staffPhotosByName();

        if ($photosByName === []) {
            return $data;
        }

        $attach = static function (?array $person) use ($photosByName): ?array {
            if ($person === null || ! isset($person['name'])) {
                return $person;
            }

            $key = trim((string) $person['name']);
            if ($key !== '' && isset($photosByName[$key])) {
                $person['photo'] = $photosByName[$key];
            }

            return $person;
        };

        $data['ceo'] = $attach($data['ceo'] ?? null) ?? $data['ceo'];

        foreach ($data['departments'] as &$dept) {
            if (isset($dept['manager'])) {
                $dept['manager'] = $attach($dept['manager']);
            }

            foreach ($dept['members'] ?? [] as $i => $member) {
                $dept['members'][$i] = $attach($member);
            }

            foreach ($dept['sub_departments'] ?? [] as $si => $sub) {
                if (isset($sub['manager'])) {
                    $dept['sub_departments'][$si]['manager'] = $attach($sub['manager']);
                }

                foreach ($sub['members'] ?? [] as $mi => $member) {
                    $dept['sub_departments'][$si]['members'][$mi] = $attach($member);
                }
            }
        }
        unset($dept);

        return $data;
    }

    /**
     * @return array<string, string> name => public URL
     */
    private static function staffPhotosByName(): array
    {
        try {
            if (! Schema::hasColumn('users', 'staff_photo')) {
                return [];
            }

            return User::query()
                ->whereNotNull('staff_photo')
                ->where('staff_photo', '!=', '')
                ->get(['name', 'staff_photo'])
                ->mapWithKeys(function (User $user): array {
                    $url = $user->staffPhotoUrl();
                    $name = trim((string) $user->name);

                    return ($url && $name !== '') ? [$name => $url] : [];
                })
                ->all();
        } catch (\Throwable) {
            return [];
        }
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
