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
     * Domain-level permission keys (underscore style) — extend here; seeder syncs to DB.
     *
     * @return list<string>
     */
    public static function domainPermissionNames(): array
    {
        return [
            'view_news',
            'manage_news',
            'manage_programs',
            'manage_partners',
            'approve_registrations',
            'issue_certificates',
            'manage_volunteers',
            'view_notifications',
            'send_notifications',
            'manage_roles',
            'assign_beneficiary_roles',
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
     * Arabic labels for Spatie roles (Filament / display).
     *
     * @return array<string, string>
     */
    public static function roleLabelsAr(): array
    {
        return [
            'admin' => 'مسؤول النظام',
            'media_pr' => 'الإعلام والعلاقات العامة',
            'media_employee' => 'موظف الإعلام',
            'pr_employee' => 'موظف العلاقات العامة',
            'training_manager' => 'مسؤول التدريب',
            'volunteering_manager' => 'مسؤول التطوع',
            'trainee' => 'متدرب',
            'volunteer' => 'متطوع',
            // Legacy roles (may still exist in DB until cleaned up)
            'staff' => 'موظف',
            'beneficiary' => 'مستفيد',
        ];
    }

    public static function roleArabicLabel(string $name): string
    {
        return self::roleLabelsAr()[$name] ?? $name;
    }

    /**
     * Arabic labels for permissions (إدارة الصلاحيات UI).
     *
     * @return array<string, string>
     */
    public static function permissionLabelsAr(): array
    {
        $map = [
            'view_news' => 'عرض الأخبار',
            'manage_news' => 'إدارة الأخبار',
            'manage_partners' => 'إدارة الشركاء',
            'manage_programs' => 'إدارة البرامج',
            'manage_roles' => 'إدارة أدوار المستخدمين',
            'assign_beneficiary_roles' => 'تعيين أدوار المستفيدين',
            'approve_registrations' => 'اعتماد التسجيلات',
            'issue_certificates' => 'إصدار الشهادات',
            'manage_volunteers' => 'إدارة المتطوعين',
            'view_notifications' => 'عرض التنبيهات',
            'send_notifications' => 'إرسال التنبيهات',
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
            'media_pr',
            'media_employee',
            'pr_employee',
            'training_manager',
            'volunteering_manager',
            'trainee',
            'volunteer',
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

        $training = array_values(array_unique(array_merge(
            array_values(array_filter($all, fn (string $p) => str_starts_with($p, 'paths.')
                || str_starts_with($p, 'courses.')
                || str_starts_with($p, 'programs.')
                || str_starts_with($p, 'registrations.')
                || str_starts_with($p, 'progress.')
                || str_starts_with($p, 'certificates.')
                || in_array($p, [
                    'users.view', 'users.create', 'users.update', 'users.activate',
                    'emails.send', 'statistics.view',
                    'view_news', 'manage_programs', 'approve_registrations', 'issue_certificates',
                    'view_notifications', 'send_notifications',
                ], true))),
            ['assign_beneficiary_roles'],
        )));

        $volunteering = array_values(array_unique(array_merge(
            array_values(array_filter($all, fn (string $p) => str_starts_with($p, 'volunteering.')
                || str_starts_with($p, 'volunteer_hours.')
                || str_starts_with($p, 'registrations.')
                || in_array($p, [
                    'certificates.view', 'certificates.download',
                    'statistics.view', 'emails.send',
                    'manage_volunteers', 'approve_registrations',
                    'view_notifications', 'send_notifications',
                ], true))),
            ['assign_beneficiary_roles'],
        )));

        $media = [
            'view_news', 'manage_news', 'view_notifications', 'emails.send', 'statistics.view',
        ];

        $prOnly = [
            'manage_partners', 'view_notifications', 'emails.send', 'statistics.view',
        ];

        $mediaPrLegacy = array_values(array_unique([...$media, ...$prOnly]));

        $portalRead = [
            'paths.view', 'courses.view', 'programs.view', 'volunteering.view',
            'registrations.view', 'progress.view',
            'volunteer_hours.view', 'volunteer_hours.create',
            'certificates.view', 'certificates.download',
            'view_notifications',
        ];

        return [
            'admin' => $all,
            'media_pr' => $mediaPrLegacy,
            'media_employee' => $media,
            'pr_employee' => $prOnly,
            'training_manager' => $training,
            'volunteering_manager' => $volunteering,
            'trainee' => $portalRead,
            'volunteer' => $portalRead,
        ];
    }
}
