<?php

namespace App\Services\Rbac;

/**
 * مصفوفة صلاحيات الموظفين — مجموعات عربية مع أعمدة CRUD.
 */
final class PermissionMatrixCatalog
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     actions: array{view?: list<string>|null, create?: list<string>|null, update?: list<string>|null, delete?: list<string>|null}
     * }>
     */
    public static function groups(): array
    {
        return [
            [
                'key' => 'users',
                'label' => 'المستخدمون',
                'actions' => [
                    'view' => ['users.view'],
                    'create' => ['users.create'],
                    'update' => ['users.update', 'users.activate', 'assign_beneficiary_roles', 'edit_profile_badges'],
                    'delete' => null,
                ],
            ],
            [
                'key' => 'paths',
                'label' => 'المسارات التعليمية',
                'actions' => [
                    'view' => ['paths.view'],
                    'create' => ['paths.create'],
                    'update' => ['paths.update', 'paths.publish', 'paths.archive'],
                    'delete' => ['paths.delete'],
                ],
            ],
            [
                'key' => 'programs',
                'label' => 'البرامج التدريبية',
                'actions' => [
                    'view' => ['programs.view', 'manage_programs'],
                    'create' => ['programs.create'],
                    'update' => ['programs.update', 'programs.publish', 'programs.archive'],
                    'delete' => ['programs.delete'],
                ],
            ],
            [
                'key' => 'courses',
                'label' => 'الدورات',
                'actions' => [
                    'view' => ['courses.view'],
                    'create' => ['courses.create'],
                    'update' => ['courses.update', 'courses.publish', 'courses.hide'],
                    'delete' => ['courses.delete'],
                ],
            ],
            [
                'key' => 'registrations',
                'label' => 'التسجيلات والاعتماد',
                'actions' => [
                    'view' => ['registrations.view', 'progress.view'],
                    'create' => null,
                    'update' => ['registrations.approve', 'registrations.reject', 'approve_registrations', 'progress.update'],
                    'delete' => null,
                ],
            ],
            [
                'key' => 'volunteering',
                'label' => 'التطوع',
                'actions' => [
                    'view' => ['volunteering.view', 'manage_volunteers'],
                    'create' => ['volunteering.create'],
                    'update' => ['volunteering.update', 'volunteering.publish', 'volunteering.archive'],
                    'delete' => ['volunteering.delete'],
                ],
            ],
            [
                'key' => 'volunteer_hours',
                'label' => 'ساعات التطوع',
                'actions' => [
                    'view' => ['volunteer_hours.view'],
                    'create' => ['volunteer_hours.create'],
                    'update' => ['volunteer_hours.approve', 'volunteer_hours.reject'],
                    'delete' => null,
                ],
            ],
            [
                'key' => 'certificates',
                'label' => 'الشهادات',
                'actions' => [
                    'view' => ['certificates.view', 'certificates.download'],
                    'create' => ['certificates.issue', 'issue_certificates'],
                    'update' => null,
                    'delete' => null,
                ],
            ],
            [
                'key' => 'news',
                'label' => 'الأخبار',
                'actions' => [
                    'view' => ['view_news'],
                    'create' => ['manage_news'],
                    'update' => ['manage_news'],
                    'delete' => ['manage_news'],
                ],
            ],
            [
                'key' => 'media',
                'label' => 'المركز الإعلامي',
                'actions' => [
                    'view' => ['manage_media'],
                    'create' => ['manage_media'],
                    'update' => ['manage_media'],
                    'delete' => ['manage_media'],
                ],
            ],
            [
                'key' => 'partners',
                'label' => 'الشركاء',
                'actions' => [
                    'view' => ['manage_partners'],
                    'create' => ['manage_partners'],
                    'update' => ['manage_partners'],
                    'delete' => ['manage_partners'],
                ],
            ],
            [
                'key' => 'governance',
                'label' => 'الحوكمة واللوائح',
                'actions' => [
                    'view' => ['manage_governance', 'manage_regulations'],
                    'create' => ['manage_governance', 'manage_regulations'],
                    'update' => ['manage_governance', 'manage_regulations'],
                    'delete' => ['manage_governance', 'manage_regulations'],
                ],
            ],
            [
                'key' => 'beneficiaries',
                'label' => 'بيانات المستفيدين',
                'actions' => [
                    'view' => ['beneficiaries.view_basic', 'beneficiaries.view_contact', 'beneficiaries.identity.view_masked', 'beneficiary.cv.view'],
                    'create' => null,
                    'update' => ['beneficiaries.update_basic', 'beneficiaries.update_sensitive', 'beneficiaries.identity.update', 'beneficiaries.deactivate'],
                    'delete' => null,
                ],
            ],
            [
                'key' => 'identity_sensitive',
                'label' => 'كشف الهوية الحساسة',
                'actions' => [
                    'view' => ['beneficiaries.identity.view_full', 'beneficiaries.identity.search_exact', 'beneficiary.cv.download'],
                    'create' => null,
                    'update' => null,
                    'delete' => null,
                ],
            ],
            [
                'key' => 'candidate_pool',
                'label' => 'قاعدة المرشحين',
                'actions' => [
                    'view' => ['candidate_pool.view', 'candidate_pool.profile.view', 'candidate_pool.contact.view', 'candidate_pool.cv.view', 'candidate_pool.cv.download'],
                    'create' => null,
                    'update' => ['candidate_pool.consent_versions.manage'],
                    'delete' => null,
                ],
            ],
            [
                'key' => 'exports',
                'label' => 'التصدير',
                'actions' => [
                    'view' => ['exports.beneficiaries.basic', 'exports.beneficiaries.contact', 'exports.training'],
                    'create' => ['exports.beneficiaries.basic', 'exports.beneficiaries.contact', 'exports.training'],
                    'update' => null,
                    'delete' => null,
                ],
            ],
            [
                'key' => 'privacy',
                'label' => 'الخصوصية وسياسة البيانات',
                'actions' => [
                    'view' => ['privacy_policy.view', 'privacy_acknowledgements.view', 'privacy_requests.view', 'privacy_requests.view_internal_notes'],
                    'create' => ['privacy_policy.create'],
                    'update' => [
                        'privacy_policy.update_draft', 'privacy_policy.publish', 'privacy_policy.archive',
                        'privacy_requests.assign', 'privacy_requests.review', 'privacy_requests.approve', 'privacy_requests.reject',
                        'privacy_requests.correction.execute', 'privacy_requests.export.review', 'privacy_requests.export.approve',
                        'privacy_requests.export.generate', 'privacy_requests.export.retry',
                    ],
                    'delete' => ['privacy_requests.execute'],
                ],
            ],
            [
                'key' => 'retention',
                'label' => 'الاحتفاظ بالبيانات',
                'actions' => [
                    'view' => ['retention_policies.view', 'retention_policies.preview', 'retention_runs.view'],
                    'create' => ['retention_policies.create'],
                    'update' => ['retention_policies.update_draft', 'retention_policies.manage', 'retention_policies.activate', 'retention_exceptions.manage'],
                    'delete' => ['retention_runs.execute'],
                ],
            ],
            [
                'key' => 'logs',
                'label' => 'سجلات التدقيق والأمن',
                'actions' => [
                    'view' => ['activity_logs.view', 'audit_logs.view', 'security_logs.view', 'security_logs.view_sensitive_metadata'],
                    'create' => null,
                    'update' => null,
                    'delete' => null,
                ],
            ],
            [
                'key' => 'comms',
                'label' => 'التنبيهات والإحصاءات',
                'actions' => [
                    'view' => ['view_notifications', 'statistics.view'],
                    'create' => ['send_notifications', 'emails.send'],
                    'update' => null,
                    'delete' => null,
                ],
            ],
            [
                'key' => 'brand',
                'label' => 'الهوية البصرية',
                'actions' => [
                    'view' => ['manage_visual_identity', 'manage_banners', 'manage_brand_settings'],
                    'create' => ['manage_visual_identity', 'manage_banners', 'manage_brand_settings'],
                    'update' => ['manage_visual_identity', 'manage_banners', 'manage_brand_settings'],
                    'delete' => ['manage_visual_identity', 'manage_banners', 'manage_brand_settings'],
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function actionKeys(): array
    {
        return ['view', 'create', 'update', 'delete'];
    }

    /**
     * أقسام العرض في واجهة المصفوفة (لتجميع المجموعات بصرياً).
     *
     * @return list<array{key: string, label: string, description: string, group_keys: list<string>}>
     */
    public static function sections(): array
    {
        return [
            [
                'key' => 'training',
                'label' => 'التدريب والبرامج',
                'description' => 'المسارات والبرامج والدورات والتسجيلات والشهادات',
                'group_keys' => ['paths', 'programs', 'courses', 'registrations', 'certificates'],
            ],
            [
                'key' => 'volunteer',
                'label' => 'التطوع',
                'description' => 'الفرص وساعات التطوع',
                'group_keys' => ['volunteering', 'volunteer_hours'],
            ],
            [
                'key' => 'content',
                'label' => 'المحتوى والنشر',
                'description' => 'الأخبار والإعلام والشركاء والحوكمة',
                'group_keys' => ['news', 'media', 'partners', 'governance', 'brand'],
            ],
            [
                'key' => 'people',
                'label' => 'المستخدمون والمستفيدون',
                'description' => 'إدارة الحسابات وبيانات المستفيدين والمرشحين',
                'group_keys' => ['users', 'beneficiaries', 'identity_sensitive', 'candidate_pool', 'exports'],
            ],
            [
                'key' => 'compliance',
                'label' => 'الخصوصية والأمان',
                'description' => 'سياسات الخصوصية والاحتفاظ والسجلات والتنبيهات',
                'group_keys' => ['privacy', 'retention', 'logs', 'comms'],
            ],
        ];
    }

    /**
     * @return list<array{section: array{key: string, label: string, description: string}, groups: list<array>}>
     */
    public static function sectionsWithGroups(): array
    {
        $byKey = [];
        foreach (self::groups() as $group) {
            $byKey[$group['key']] = $group;
        }

        $out = [];
        foreach (self::sections() as $section) {
            $groups = [];
            foreach ($section['group_keys'] as $key) {
                if (isset($byKey[$key])) {
                    $groups[] = $byKey[$key];
                }
            }
            if ($groups === []) {
                continue;
            }
            $out[] = [
                'section' => [
                    'key' => $section['key'],
                    'label' => $section['label'],
                    'description' => $section['description'],
                ],
                'groups' => $groups,
            ];
        }

        return $out;
    }

    /** @return array<string, string> */
    public static function actionLabelsAr(): array
    {
        return [
            'view' => 'عرض',
            'create' => 'إنشاء',
            'update' => 'تعديل',
            'delete' => 'حذف',
        ];
    }

    /** @return list<string> */
    public static function assignablePermissionNames(): array
    {
        $names = [];

        foreach (self::groups() as $group) {
            foreach (self::actionKeys() as $action) {
                $perms = $group['actions'][$action] ?? null;
                if (! is_array($perms)) {
                    continue;
                }
                foreach ($perms as $perm) {
                    $names[] = $perm;
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  list<string>  $ownedPermissions
     * @return array<string, array<string, bool>>
     */
    public static function checkboxStateFromPermissions(array $ownedPermissions): array
    {
        $owned = array_fill_keys($ownedPermissions, true);
        $state = [];

        foreach (self::groups() as $group) {
            $row = [];
            $available = 0;
            $checked = 0;

            foreach (self::actionKeys() as $action) {
                $perms = $group['actions'][$action] ?? null;
                if (! is_array($perms) || $perms === []) {
                    $row[$action] = false;
                    $row[$action.'_enabled'] = false;

                    continue;
                }

                $row[$action.'_enabled'] = true;
                $available++;
                $on = collect($perms)->every(fn (string $p): bool => isset($owned[$p]));
                $row[$action] = $on;
                if ($on) {
                    $checked++;
                }
            }

            $row['all'] = $available > 0 && $checked === $available;
            $state[$group['key']] = $row;
        }

        return $state;
    }

    /**
     * @param  array<string, array<string, bool>>  $checkboxState
     * @return list<string>
     */
    public static function permissionsFromCheckboxState(array $checkboxState): array
    {
        $names = [];

        foreach (self::groups() as $group) {
            $row = $checkboxState[$group['key']] ?? [];
            foreach (self::actionKeys() as $action) {
                if (! ($row[$action] ?? false)) {
                    continue;
                }
                $perms = $group['actions'][$action] ?? null;
                if (! is_array($perms)) {
                    continue;
                }
                foreach ($perms as $perm) {
                    $names[] = $perm;
                }
            }
        }

        return array_values(array_unique($names));
    }
}
