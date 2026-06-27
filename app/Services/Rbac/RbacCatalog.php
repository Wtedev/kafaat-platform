<?php

namespace App\Services\Rbac;

/**
 * Single source of truth for RBAC seed data (permissions + role → permission map).
 * Runtime checks use the database; this class only drives seeding and Arabic labels in the UI.
 */
final class RbacCatalog
{
    public const GUARD_WEB = 'web';

    /**
     * @return list<string>
     */
    public static function domainPermissionNames(): array
    {
        return [
            'view_news',
            'manage_news',
            'manage_media',
            'manage_regulations',
            'manage_governance',
            'manage_programs',
            'manage_partners',
            'approve_registrations',
            'issue_certificates',
            'manage_volunteers',
            'view_notifications',
            'send_notifications',
            'manage_roles',
            'assign_beneficiary_roles',
            'edit_profile_badges',
            'manage_visual_identity',
            'manage_banners',
            'manage_brand_settings',
            'privacy_policy.view',
            'privacy_policy.create',
            'privacy_policy.update_draft',
            'privacy_policy.publish',
            'privacy_policy.archive',
        ];
    }

    /**
     * Fine-grained permissions used by policies and Filament (dotted notation).
     *
     * @return list<string>
     */
    public static function legacyPermissionNames(): array
    {
        return [
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.activate',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'paths.view', 'paths.create', 'paths.update', 'paths.delete', 'paths.publish', 'paths.archive',
            'courses.view', 'courses.create', 'courses.update', 'courses.delete', 'courses.publish', 'courses.hide',
            'programs.view', 'programs.create', 'programs.update', 'programs.delete', 'programs.publish', 'programs.archive',
            'volunteering.view', 'volunteering.create', 'volunteering.update', 'volunteering.delete', 'volunteering.publish', 'volunteering.archive',
            'registrations.view', 'registrations.approve', 'registrations.reject',
            'progress.view', 'progress.update',
            'volunteer_hours.view', 'volunteer_hours.create', 'volunteer_hours.approve', 'volunteer_hours.reject',
            'certificates.view', 'certificates.issue', 'certificates.download',
            'emails.send',
            'statistics.view',
        ];
    }

    /**
     * @return list<string>
     */
    public static function allPermissionNames(): array
    {
        return array_values(array_unique([
            ...self::legacyPermissionNames(),
            ...self::domainPermissionNames(),
        ]));
    }

    /**
     * أدوار الموظفين القابلة للتعيين من واجهة المستخدمين (بدون admin).
     *
     * @return list<string>
     */
    public static function staffRoleNames(): array
    {
        return [
            'technical_admin',
            'training_management',
            'volunteer_management',
            'programs_management',
            'media_management',
            'public_relations',
            'visual_identity',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function roleLabelsAr(): array
    {
        return [
            'admin' => 'مسؤول النظام',
            'technical_admin' => 'المسؤول التقني',
            'training_management' => 'إدارة التدريب',
            'volunteer_management' => 'إدارة التطوع',
            'programs_management' => 'إدارة البرامج',
            'media_management' => 'إدارة الإعلام',
            'public_relations' => 'إدارة العلاقات العامة',
            'visual_identity' => 'إدارة الهوية البصرية',
            'trainee' => 'متدرب',
            'volunteer' => 'متطوع',
        ];
    }

    public static function roleArabicLabel(string $name): string
    {
        return self::roleLabelsAr()[$name] ?? $name;
    }

    /**
     * @return array<string, string>
     */
    public static function permissionLabelsAr(): array
    {
        $map = [
            'view_news' => 'عرض الأخبار',
            'manage_news' => 'إدارة الأخبار',
            'manage_media' => 'إدارة المركز الإعلامي (الصور)',
            'manage_regulations' => 'إدارة اللوائح والأنظمة',
            'manage_governance' => 'إدارة الحوكمة',
            'manage_partners' => 'إدارة الشركاء',
            'manage_programs' => 'إدارة البرامج',
            'manage_roles' => 'إدارة أدوار المستخدمين',
            'assign_beneficiary_roles' => 'تعيين أدوار المستفيدين',
            'edit_profile_badges' => 'تعديل شارات المستفيد',
            'approve_registrations' => 'اعتماد التسجيلات',
            'issue_certificates' => 'إصدار الشهادات',
            'manage_volunteers' => 'إدارة المتطوعين',
            'view_notifications' => 'عرض التنبيهات',
            'send_notifications' => 'إرسال التنبيهات',
            'manage_visual_identity' => 'إدارة الهوية البصرية',
            'manage_banners' => 'إدارة البنرات',
            'manage_brand_settings' => 'تخصيص الألوان والتصميم',
            'privacy_policy.view' => 'عرض إصدارات سياسة الخصوصية',
            'privacy_policy.create' => 'إنشاء مسودة سياسة خصوصية',
            'privacy_policy.update_draft' => 'تعديل مسودات سياسة الخصوصية',
            'privacy_policy.publish' => 'نشر إصدارات سياسة الخصوصية',
            'privacy_policy.archive' => 'أرشفة إصدارات سياسة الخصوصية',
            'users.view' => 'عرض المستخدمين',
            'users.create' => 'إنشاء مستخدمين',
            'users.update' => 'تعديل المستخدمين',
            'users.delete' => 'حذف المستخدمين',
            'users.activate' => 'تفعيل المستخدمين',
            'roles.view' => 'عرض الأدوار',
            'roles.create' => 'إنشاء أدوار',
            'roles.update' => 'تعديل الأدوار',
            'roles.delete' => 'حذف الأدوار',
            'paths.view' => 'عرض المسارات',
            'paths.create' => 'إنشاء مسارات',
            'paths.update' => 'تعديل المسارات',
            'paths.delete' => 'حذف المسارات',
            'paths.publish' => 'نشر المسارات',
            'paths.archive' => 'أرشفة المسارات',
            'courses.view' => 'عرض الدورات',
            'courses.create' => 'إنشاء دورات',
            'courses.update' => 'تعديل الدورات',
            'courses.delete' => 'حذف الدورات',
            'courses.publish' => 'نشر الدورات',
            'courses.hide' => 'إخفاء الدورات',
            'programs.view' => 'عرض البرامج',
            'programs.create' => 'إنشاء برامج',
            'programs.update' => 'تعديل البرامج',
            'programs.delete' => 'حذف البرامج',
            'programs.publish' => 'نشر البرامج',
            'programs.archive' => 'أرشفة البرامج',
            'volunteering.view' => 'عرض التطوع',
            'volunteering.create' => 'إنشاء فرص تطوع',
            'volunteering.update' => 'تعديل التطوع',
            'volunteering.delete' => 'حذف التطوع',
            'volunteering.publish' => 'نشر التطوع',
            'volunteering.archive' => 'أرشفة التطوع',
            'registrations.view' => 'عرض التسجيلات',
            'registrations.approve' => 'اعتماد التسجيلات (تفصيلي)',
            'registrations.reject' => 'رفض التسجيلات',
            'progress.view' => 'عرض التقدم',
            'progress.update' => 'تحديث التقدم',
            'volunteer_hours.view' => 'عرض ساعات التطوع',
            'volunteer_hours.create' => 'تسجيل ساعات تطوع',
            'volunteer_hours.approve' => 'اعتماد ساعات التطوع',
            'volunteer_hours.reject' => 'رفض ساعات التطوع',
            'certificates.view' => 'عرض الشهادات',
            'certificates.issue' => 'إصدار الشهادات (تفصيلي)',
            'certificates.download' => 'تحميل الشهادات',
            'emails.send' => 'إرسال البريد',
            'statistics.view' => 'عرض الإحصاءات',
        ];

        return $map;
    }

    public static function permissionArabicLabel(string $name): string
    {
        return self::permissionLabelsAr()[$name] ?? $name;
    }

    /**
     * Application role names (synced to `roles` table).
     *
     * @return list<string>
     */
    public static function applicationRoleNames(): array
    {
        return [
            'admin',
            ...self::staffRoleNames(),
            'trainee',
            'volunteer',
        ];
    }

    /**
     * Map legacy Spatie role names to the new catalog (for migrations / display).
     *
     * @return array<string, string>
     */
    public static function legacyRoleMigrationMap(): array
    {
        return [
            'media_pr' => 'media_management',
            'media' => 'media_management',
            'media_employee' => 'media_management',
            'pr_employee' => 'public_relations',
            'training_enablement_manager' => 'training_management',
            'training_manager' => 'training_management',
            'programs_activities_manager' => 'training_management',
            'volunteering_manager' => 'volunteer_management',
            'volunteer_manager' => 'volunteer_management',
            'staff' => 'programs_management',
            'beneficiary' => 'trainee',
        ];
    }

    /**
     * @return list<string>
     */
    public static function trainingDomainStaffRoleNames(): array
    {
        return [
            'admin',
            'technical_admin',
            'training_management',
            'programs_management',
        ];
    }

    /**
     * @return list<string>
     */
    public static function volunteerDomainStaffRoleNames(): array
    {
        return [
            'admin',
            'technical_admin',
            'training_management',
            'volunteer_management',
        ];
    }

    /**
     * Permission names per role (dynamic mapping — edit here, not scattered in code).
     *
     * @return array<string, list<string>>
     */
    public static function rolePermissionMatrix(): array
    {
        $all = self::allPermissionNames();

        $staffShared = [
            'view_notifications', 'send_notifications', 'emails.send', 'statistics.view',
            'assign_beneficiary_roles', 'edit_profile_badges',
        ];

        $pathsPrograms = array_values(array_unique(array_merge(
            array_values(array_filter($all, fn (string $p) => str_starts_with($p, 'paths.')
                || str_starts_with($p, 'courses.')
                || str_starts_with($p, 'programs.')
                || str_starts_with($p, 'registrations.')
                || str_starts_with($p, 'progress.')
                || in_array($p, [
                    'certificates.view', 'certificates.issue', 'certificates.download',
                    'approve_registrations', 'issue_certificates', 'manage_programs',
                ], true))),
            ['users.view', 'users.create', 'users.update', 'users.activate'],
            $staffShared,
        )));

        $volunteering = array_values(array_unique(array_merge(
            array_values(array_filter($all, fn (string $p) => str_starts_with($p, 'volunteering.')
                || str_starts_with($p, 'volunteer_hours.')
                || in_array($p, [
                    'registrations.view', 'registrations.approve', 'registrations.reject',
                    'certificates.view', 'certificates.download',
                    'manage_volunteers', 'approve_registrations',
                ], true))),
            ['users.view', 'users.create', 'users.update', 'users.activate'],
            $staffShared,
        )));

        $media = array_values(array_unique([
            'view_news', 'manage_news', 'manage_media',
            'view_notifications', 'emails.send', 'statistics.view',
        ]));

        $publicRelations = array_values(array_unique([
            'manage_partners', 'manage_regulations', 'manage_governance',
            'privacy_policy.view', 'privacy_policy.create', 'privacy_policy.update_draft', 'privacy_policy.publish',
            'view_notifications', 'emails.send', 'statistics.view',
        ]));

        $visualIdentityExtras = [
            'manage_visual_identity', 'manage_banners', 'manage_brand_settings',
        ];

        $portalRead = [
            'paths.view', 'courses.view', 'programs.view', 'volunteering.view',
            'registrations.view', 'progress.view',
            'volunteer_hours.view', 'volunteer_hours.create',
            'certificates.view', 'certificates.download',
            'view_notifications',
        ];

        return [
            'admin' => $all,
            'technical_admin' => $all,
            'training_management' => array_values(array_unique([...$pathsPrograms, ...$volunteering])),
            'volunteer_management' => $volunteering,
            'programs_management' => $pathsPrograms,
            'media_management' => $media,
            'public_relations' => $publicRelations,
            'visual_identity' => array_values(array_unique([...$all, ...$visualIdentityExtras])),
            'trainee' => $portalRead,
            'volunteer' => $portalRead,
        ];
    }
}
