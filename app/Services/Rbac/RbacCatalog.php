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
            'privacy_acknowledgements.view',
            'beneficiaries.view_basic',
            'beneficiaries.view_contact',
            'beneficiaries.update_basic',
            'beneficiaries.update_sensitive',
            'beneficiaries.deactivate',
            'beneficiaries.identity.view_masked',
            'beneficiaries.identity.view_full',
            'beneficiaries.identity.update',
            'beneficiaries.identity.search_exact',
            'beneficiary.cv.download',
            'beneficiary.cv.view',
            'candidate_pool.view',
            'candidate_pool.profile.view',
            'candidate_pool.contact.view',
            'candidate_pool.cv.view',
            'candidate_pool.cv.download',
            'candidate_pool.consent_versions.manage',
            'exports.beneficiaries.basic',
            'exports.beneficiaries.contact',
            'exports.training',
            'activity_logs.view',
            'audit_logs.view',
            'security_logs.view',
            'security_logs.view_sensitive_metadata',
            'privacy_requests.execute',
            'privacy_requests.view',
            'privacy_requests.assign',
            'privacy_requests.review',
            'privacy_requests.approve',
            'privacy_requests.reject',
            'privacy_requests.correction.execute',
            'privacy_requests.export.review',
            'privacy_requests.export.approve',
            'privacy_requests.export.generate',
            'privacy_requests.export.retry',
            'privacy_requests.view_internal_notes',
            'retention_policies.view',
            'retention_policies.manage',
            'retention_policies.activate',
            'retention_policies.preview',
            'retention_exceptions.manage',
            'permissions.assign',
        ];
    }

    /**
     * Permissions that must not be granted automatically to broad admin roles.
     *
     * @return list<string>
     */
    public static function permissionsExcludedFromBroadRoles(): array
    {
        return [
            'beneficiaries.identity.view_full',
            'beneficiaries.identity.search_exact',
            'security_logs.view_sensitive_metadata',
            'users.delete',
            'privacy_requests.execute',
            'privacy_requests.approve',
            'privacy_requests.reject',
            'retention_policies.manage',
            'retention_policies.activate',
            'retention_exceptions.manage',
        ];
    }

    /**
     * @return list<string>
     */
    public static function permissionsForBroadAdminRoles(): array
    {
        return array_values(array_diff(
            self::allPermissionNames(),
            self::permissionsExcludedFromBroadRoles(),
        ));
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
            'privacy_officer' => 'مسؤول الخصوصية',
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
            'beneficiary.cv.download' => 'تنزيل سيرة مستفيد (ملف مرفوع)',
            'beneficiary.cv.view' => 'عرض بيانات السيرة (بدون تنزيل)',
            'beneficiaries.view_basic' => 'عرض بيانات المستفيد الأساسية',
            'beneficiaries.view_contact' => 'عرض بيانات التواصل للمستفيد',
            'beneficiaries.update_basic' => 'تعديل بيانات المستفيد الأساسية',
            'beneficiaries.update_sensitive' => 'تعديل بيانات المستفيد الحساسة',
            'beneficiaries.deactivate' => 'تعطيل حساب مستفيد',
            'beneficiaries.identity.view_masked' => 'عرض الهوية مقنعة',
            'beneficiaries.identity.view_full' => 'كشف رقم الهوية الكامل',
            'beneficiaries.identity.update' => 'تعديل رقم الهوية',
            'beneficiaries.identity.search_exact' => 'بحث دقيق برقم الهوية',
            'candidate_pool.view' => 'عرض قاعدة المرشحين',
            'candidate_pool.profile.view' => 'عرض ملف مرشح',
            'candidate_pool.contact.view' => 'عرض بيانات تواصل المرشح',
            'candidate_pool.cv.view' => 'عرض بيانات سيرة المرشح',
            'candidate_pool.cv.download' => 'تنزيل سيرة مرشح',
            'candidate_pool.consent_versions.manage' => 'إدارة نص موافقة قاعدة المرشحين',
            'exports.beneficiaries.basic' => 'تصدير بيانات المستفيدين الأساسية',
            'exports.beneficiaries.contact' => 'تصدير بيانات التواصل للمستفيدين',
            'exports.training' => 'تصدير بيانات التدريب',
            'privacy_acknowledgements.view' => 'عرض إقرارات سياسة الخصوصية',
            'activity_logs.view' => 'عرض سجل نشاط المستفيدين',
            'audit_logs.view' => 'عرض سجل التدقيق',
            'security_logs.view' => 'عرض سجل الأحداث الأمنية',
            'security_logs.view_sensitive_metadata' => 'عرض بيانات إضافية في سجل الأمن',
            'privacy_requests.execute' => 'تنفيذ حذف الحساب المعتمد (سير عمل الخصوصية)',
            'privacy_requests.view' => 'عرض طلبات الخصوصية',
            'privacy_requests.assign' => 'تعيين طلبات الخصوصية',
            'privacy_requests.review' => 'مراجعة طلبات الخصوصية',
            'privacy_requests.approve' => 'اعتماد طلبات الخصوصية',
            'privacy_requests.reject' => 'رفض طلبات الخصوصية',
            'privacy_requests.correction.execute' => 'تنفيذ تصحيح بيانات المستفيد المعتمد',
            'privacy_requests.export.review' => 'مراجعة طلبات تصدير بيانات المستفيد',
            'privacy_requests.export.approve' => 'اعتماد طلبات تصدير بيانات المستفيد',
            'privacy_requests.export.generate' => 'توليد ملف تصدير بيانات المستفيد',
            'privacy_requests.export.retry' => 'إعادة محاولة توليد تصدير البيانات',
            'privacy_requests.view_internal_notes' => 'عرض ملاحظات طلبات الخصوصية الداخلية',
            'retention_policies.view' => 'عرض سياسات الاحتفاظ',
            'retention_policies.manage' => 'إدارة سياسات الاحتفاظ',
            'retention_policies.activate' => 'تفعيل سياسات الاحتفاظ',
            'retention_policies.preview' => 'معاينة سياسات الاحتفاظ',
            'retention_exceptions.manage' => 'إدارة استثناءات الاحتفاظ',
            'permissions.assign' => 'تعيين الصلاحيات',
            'users.view' => 'عرض المستخدمين',
            'users.create' => 'إنشاء مستخدمين',
            'users.update' => 'تعديل المستخدمين',
            'users.delete' => 'حذف المستخدمين (معطّل — استخدم سير عمل الخصوصية)',
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
            'privacy_officer',
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
        $broadAdmin = self::permissionsForBroadAdminRoles();

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
            'privacy_policy.view', 'privacy_policy.create', 'privacy_policy.update_draft',             'privacy_policy.publish',
            'candidate_pool.consent_versions.manage',
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

        $trainingManagement = array_values(array_unique([...$pathsPrograms, ...$volunteering,
            'candidate_pool.view', 'candidate_pool.profile.view', 'candidate_pool.cv.view',
            'beneficiaries.view_basic', 'beneficiaries.view_contact', 'beneficiaries.identity.view_masked',
            'exports.beneficiaries.basic',
        ]));

        $privacyOfficer = [
            'privacy_requests.view',
            'privacy_requests.assign',
            'privacy_requests.review',
            'privacy_requests.approve',
            'privacy_requests.reject',
            'privacy_requests.correction.execute',
            'privacy_requests.export.review',
            'privacy_requests.export.approve',
            'privacy_requests.export.generate',
            'privacy_requests.export.retry',
            'privacy_requests.view_internal_notes',
            'retention_policies.view',
            'retention_policies.manage',
            'retention_policies.preview',
            'retention_exceptions.manage',
            'privacy_policy.view',
            'privacy_acknowledgements.view',
            'beneficiaries.view_basic',
            'audit_logs.view',
            'users.view',
            'view_notifications',
        ];

        return [
            'admin' => $broadAdmin,
            'technical_admin' => $broadAdmin,
            'privacy_officer' => $privacyOfficer,
            'training_management' => $trainingManagement,
            'volunteer_management' => $volunteering,
            'programs_management' => $pathsPrograms,
            'media_management' => $media,
            'public_relations' => $publicRelations,
            'visual_identity' => array_values(array_unique([...$broadAdmin, ...$visualIdentityExtras])),
            'trainee' => $portalRead,
            'volunteer' => $portalRead,
        ];
    }
}
