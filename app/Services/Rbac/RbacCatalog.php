<?php

namespace App\Services\Rbac;

/**
 * مصدر أدوار المنصة الأربعة + الصلاحيات التقنية.
 * صلاحيات الموظفين تُدار مباشرة عبر {@see PermissionMatrixCatalog}.
 */
final class RbacCatalog
{
    public const GUARD_WEB = 'web';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_STAFF = 'staff';

    public const ROLE_BENEFICIARY = 'beneficiary';

    public const ROLE_VOLUNTEER = 'volunteer';

    /** @return list<string> */
    public static function domainPermissionNames(): array
    {
        return [
            'view_news', 'manage_news', 'manage_media', 'manage_regulations', 'manage_governance',
            'manage_programs', 'manage_partners', 'approve_registrations', 'issue_certificates',
            'manage_volunteers', 'view_notifications', 'send_notifications', 'manage_roles',
            'assign_beneficiary_roles', 'edit_profile_badges', 'manage_visual_identity',
            'manage_banners', 'manage_brand_settings',
            'privacy_policy.view', 'privacy_policy.create', 'privacy_policy.update_draft',
            'privacy_policy.publish', 'privacy_policy.archive', 'privacy_acknowledgements.view',
            'beneficiaries.view_basic', 'beneficiaries.view_contact', 'beneficiaries.update_basic',
            'beneficiaries.update_sensitive', 'beneficiaries.deactivate',
            'beneficiaries.identity.view_masked', 'beneficiaries.identity.view_full',
            'beneficiaries.identity.update', 'beneficiaries.identity.search_exact',
            'beneficiary.cv.download', 'beneficiary.cv.view',
            'candidate_pool.view', 'candidate_pool.profile.view', 'candidate_pool.contact.view',
            'candidate_pool.cv.view', 'candidate_pool.cv.download', 'candidate_pool.consent_versions.manage',
            'exports.beneficiaries.basic', 'exports.beneficiaries.contact', 'exports.training',
            'activity_logs.view', 'audit_logs.view', 'security_logs.view', 'security_logs.view_sensitive_metadata',
            'privacy_requests.execute', 'privacy_requests.view', 'privacy_requests.assign',
            'privacy_requests.review', 'privacy_requests.approve', 'privacy_requests.reject',
            'privacy_requests.correction.execute', 'privacy_requests.export.review',
            'privacy_requests.export.approve', 'privacy_requests.export.generate',
            'privacy_requests.export.retry', 'privacy_requests.view_internal_notes',
            'retention_policies.view', 'retention_policies.create', 'retention_policies.update_draft',
            'retention_policies.manage', 'retention_policies.activate', 'retention_policies.preview',
            'retention_runs.view', 'retention_runs.execute', 'retention_exceptions.manage',
            'permissions.assign',
        ];
    }

    /** @return list<string> */
    public static function adminOnlyPermissionNames(): array
    {
        return [
            'manage_roles', 'permissions.assign',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'users.delete',
        ];
    }

    /** @return list<string> */
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
            'emails.send', 'statistics.view',
        ];
    }

    /** @return list<string> */
    public static function allPermissionNames(): array
    {
        return array_values(array_unique([
            ...self::legacyPermissionNames(),
            ...self::domainPermissionNames(),
        ]));
    }

    /** @return list<string> */
    public static function applicationRoleNames(): array
    {
        return [
            self::ROLE_ADMIN,
            self::ROLE_STAFF,
            self::ROLE_BENEFICIARY,
            self::ROLE_VOLUNTEER,
        ];
    }

    /** @return list<string> */
    public static function staffRoleNames(): array
    {
        return [self::ROLE_STAFF];
    }

    /** @return array<string, string> */
    public static function roleLabelsAr(): array
    {
        return [
            self::ROLE_ADMIN => 'أدمن',
            self::ROLE_STAFF => 'موظف',
            self::ROLE_BENEFICIARY => 'مستفيد',
            self::ROLE_VOLUNTEER => 'فريق تطوعي',
            'technical_admin' => 'موظف',
            'training_management' => 'موظف',
            'volunteer_management' => 'موظف',
            'programs_management' => 'موظف',
            'media_management' => 'موظف',
            'public_relations' => 'موظف',
            'visual_identity' => 'موظف',
            'privacy_officer' => 'موظف',
            'trainee' => 'مستفيد',
        ];
    }

    public static function roleArabicLabel(string $name): string
    {
        return self::roleLabelsAr()[$name] ?? $name;
    }

    /** @return array<string, string> */
    public static function permissionLabelsAr(): array
    {
        $labels = [];
        foreach (self::allPermissionNames() as $name) {
            $labels[$name] = match (true) {
                default => $name,
            };
        }

        // Keep rich Arabic map for known keys
        $map = [
            'view_news' => 'عرض الأخبار', 'manage_news' => 'إدارة الأخبار', 'manage_media' => 'إدارة المركز الإعلامي',
            'manage_regulations' => 'إدارة اللوائح', 'manage_governance' => 'إدارة الحوكمة',
            'manage_partners' => 'إدارة الشركاء', 'manage_programs' => 'إدارة البرامج',
            'manage_roles' => 'إدارة أدوار المستخدمين', 'assign_beneficiary_roles' => 'تعيين أدوار المستفيدين',
            'edit_profile_badges' => 'تعديل شارات المستفيد', 'approve_registrations' => 'اعتماد التسجيلات',
            'issue_certificates' => 'إصدار الشهادات', 'manage_volunteers' => 'إدارة المتطوعين',
            'view_notifications' => 'عرض التنبيهات', 'send_notifications' => 'إرسال التنبيهات',
            'manage_visual_identity' => 'إدارة الهوية البصرية', 'manage_banners' => 'إدارة البنرات',
            'manage_brand_settings' => 'تخصيص الألوان والتصميم',
            'privacy_policy.view' => 'عرض سياسة الخصوصية', 'privacy_policy.create' => 'إنشاء سياسة خصوصية',
            'privacy_policy.update_draft' => 'تعديل مسودة الخصوصية', 'privacy_policy.publish' => 'نشر سياسة الخصوصية',
            'privacy_policy.archive' => 'أرشفة سياسة الخصوصية',
            'beneficiary.cv.download' => 'تنزيل سيرة مستفيد', 'beneficiary.cv.view' => 'عرض بيانات السيرة',
            'beneficiaries.view_basic' => 'عرض بيانات المستفيد', 'beneficiaries.view_contact' => 'عرض تواصل المستفيد',
            'beneficiaries.update_basic' => 'تعديل بيانات المستفيد', 'beneficiaries.update_sensitive' => 'تعديل بيانات حساسة',
            'beneficiaries.deactivate' => 'تعطيل مستفيد',
            'beneficiaries.identity.view_masked' => 'عرض هوية مقنعة', 'beneficiaries.identity.view_full' => 'كشف الهوية',
            'beneficiaries.identity.update' => 'تعديل الهوية', 'beneficiaries.identity.search_exact' => 'بحث بالهوية',
            'candidate_pool.view' => 'عرض قاعدة المرشحين', 'candidate_pool.profile.view' => 'عرض ملف مرشح',
            'candidate_pool.contact.view' => 'عرض تواصل مرشح', 'candidate_pool.cv.view' => 'عرض سيرة مرشح',
            'candidate_pool.cv.download' => 'تنزيل سيرة مرشح', 'candidate_pool.consent_versions.manage' => 'إدارة موافقة المرشحين',
            'exports.beneficiaries.basic' => 'تصدير مستفيدين', 'exports.beneficiaries.contact' => 'تصدير تواصل مستفيدين',
            'exports.training' => 'تصدير تدريب', 'privacy_acknowledgements.view' => 'عرض إقرارات الخصوصية',
            'activity_logs.view' => 'عرض سجل النشاط', 'audit_logs.view' => 'عرض سجل التدقيق',
            'security_logs.view' => 'عرض سجل الأمن', 'security_logs.view_sensitive_metadata' => 'بيانات أمن إضافية',
            'privacy_requests.execute' => 'تنفيذ حذف خصوصية', 'privacy_requests.view' => 'عرض طلبات الخصوصية',
            'privacy_requests.assign' => 'تعيين طلبات الخصوصية', 'privacy_requests.review' => 'مراجعة طلبات الخصوصية',
            'privacy_requests.approve' => 'اعتماد طلبات الخصوصية', 'privacy_requests.reject' => 'رفض طلبات الخصوصية',
            'privacy_requests.correction.execute' => 'تنفيذ تصحيح بيانات', 'privacy_requests.export.review' => 'مراجعة تصدير خصوصية',
            'privacy_requests.export.approve' => 'اعتماد تصدير خصوصية', 'privacy_requests.export.generate' => 'توليد تصدير خصوصية',
            'privacy_requests.export.retry' => 'إعادة تصدير خصوصية', 'privacy_requests.view_internal_notes' => 'ملاحظات خصوصية داخلية',
            'retention_policies.view' => 'عرض سياسات الاحتفاظ', 'retention_policies.create' => 'إنشاء سياسات احتفاظ',
            'retention_policies.update_draft' => 'تعديل مسودات احتفاظ', 'retention_policies.manage' => 'إدارة الاحتفاظ',
            'retention_policies.activate' => 'تفعيل الاحتفاظ', 'retention_policies.preview' => 'معاينة الاحتفاظ',
            'retention_runs.view' => 'عرض عمليات الاحتفاظ', 'retention_runs.execute' => 'تنفيذ عمليات الاحتفاظ',
            'retention_exceptions.manage' => 'استثناءات الاحتفاظ', 'permissions.assign' => 'تعيين الصلاحيات',
            'users.view' => 'عرض المستخدمين', 'users.create' => 'إنشاء مستخدمين', 'users.update' => 'تعديل المستخدمين',
            'users.delete' => 'حذف المستخدمين', 'users.activate' => 'تفعيل المستخدمين',
            'roles.view' => 'عرض الأدوار', 'roles.create' => 'إنشاء أدوار', 'roles.update' => 'تعديل الأدوار', 'roles.delete' => 'حذف الأدوار',
            'paths.view' => 'عرض المسارات', 'paths.create' => 'إنشاء مسارات', 'paths.update' => 'تعديل المسارات',
            'paths.delete' => 'حذف المسارات', 'paths.publish' => 'نشر المسارات', 'paths.archive' => 'أرشفة المسارات',
            'courses.view' => 'عرض الدورات', 'courses.create' => 'إنشاء دورات', 'courses.update' => 'تعديل الدورات',
            'courses.delete' => 'حذف الدورات', 'courses.publish' => 'نشر الدورات', 'courses.hide' => 'إخفاء الدورات',
            'programs.view' => 'عرض البرامج', 'programs.create' => 'إنشاء برامج', 'programs.update' => 'تعديل البرامج',
            'programs.delete' => 'حذف البرامج', 'programs.publish' => 'نشر البرامج', 'programs.archive' => 'أرشفة البرامج',
            'volunteering.view' => 'عرض التطوع', 'volunteering.create' => 'إنشاء تطوع', 'volunteering.update' => 'تعديل التطوع',
            'volunteering.delete' => 'حذف التطوع', 'volunteering.publish' => 'نشر التطوع', 'volunteering.archive' => 'أرشفة التطوع',
            'registrations.view' => 'عرض التسجيلات', 'registrations.approve' => 'اعتماد التسجيلات', 'registrations.reject' => 'رفض التسجيلات',
            'progress.view' => 'عرض التقدم', 'progress.update' => 'تحديث التقدم',
            'volunteer_hours.view' => 'عرض ساعات التطوع', 'volunteer_hours.create' => 'تسجيل ساعات تطوع',
            'volunteer_hours.approve' => 'اعتماد ساعات التطوع', 'volunteer_hours.reject' => 'رفض ساعات التطوع',
            'certificates.view' => 'عرض الشهادات', 'certificates.issue' => 'إصدار الشهادات', 'certificates.download' => 'تحميل الشهادات',
            'emails.send' => 'إرسال البريد', 'statistics.view' => 'عرض الإحصاءات',
        ];

        return $map + $labels;
    }

    public static function permissionArabicLabel(string $name): string
    {
        return self::permissionLabelsAr()[$name] ?? $name;
    }

    /** @return array<string, string> */
    public static function legacyRoleMigrationMap(): array
    {
        return [
            'media_pr' => self::ROLE_STAFF, 'media' => self::ROLE_STAFF, 'media_employee' => self::ROLE_STAFF,
            'pr_employee' => self::ROLE_STAFF, 'training_enablement_manager' => self::ROLE_STAFF,
            'training_manager' => self::ROLE_STAFF, 'programs_activities_manager' => self::ROLE_STAFF,
            'volunteering_manager' => self::ROLE_STAFF, 'volunteer_manager' => self::ROLE_STAFF,
            'technical_admin' => self::ROLE_STAFF, 'training_management' => self::ROLE_STAFF,
            'volunteer_management' => self::ROLE_STAFF, 'programs_management' => self::ROLE_STAFF,
            'media_management' => self::ROLE_STAFF, 'public_relations' => self::ROLE_STAFF,
            'visual_identity' => self::ROLE_STAFF, 'privacy_officer' => self::ROLE_STAFF,
            'trainee' => self::ROLE_BENEFICIARY, 'beneficiary' => self::ROLE_BENEFICIARY,
        ];
    }

    /** @return list<string> */
    public static function trainingDomainStaffRoleNames(): array
    {
        return [self::ROLE_ADMIN, self::ROLE_STAFF];
    }

    /** @return list<string> */
    public static function volunteerDomainStaffRoleNames(): array
    {
        return [self::ROLE_ADMIN, self::ROLE_STAFF];
    }

    /** @return array<string, list<string>> */
    public static function rolePermissionMatrix(): array
    {
        $portalRead = [
            'paths.view', 'courses.view', 'programs.view', 'volunteering.view',
            'registrations.view', 'progress.view',
            'volunteer_hours.view', 'volunteer_hours.create',
            'certificates.view', 'certificates.download',
            'view_notifications',
        ];

        return [
            self::ROLE_ADMIN => self::allPermissionNames(),
            self::ROLE_STAFF => [],
            self::ROLE_BENEFICIARY => $portalRead,
            self::ROLE_VOLUNTEER => $portalRead,
        ];
    }

    /** @return list<string> */
    public static function permissionsExcludedFromBroadRoles(): array
    {
        return self::adminOnlyPermissionNames();
    }

    /** @return list<string> */
    public static function permissionsForBroadAdminRoles(): array
    {
        return self::allPermissionNames();
    }
}
