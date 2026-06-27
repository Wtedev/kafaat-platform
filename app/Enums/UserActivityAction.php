<?php

namespace App\Enums;

enum UserActivityAction: string
{
    case AccountCreated = 'account_created';
    case Login = 'login';
    case Logout = 'logout';
    case EmailVerified = 'email_verified';
    case EmailNotificationsEnabled = 'email_notifications_enabled';
    case EmailNotificationsDisabled = 'email_notifications_disabled';
    case ProfileUpdated = 'profile_updated';
    case CompetencyUpdated = 'competency_updated';
    case AdminUserUpdated = 'admin_user_updated';
    case ProgramRegistration = 'program_registration';
    case PathRegistration = 'path_registration';
    case VolunteerRegistration = 'volunteer_registration';
    case AttendanceCheckIn = 'attendance_check_in';
    case CertificateDownloaded = 'certificate_downloaded';
    case CvUploaded = 'cv_uploaded';
    case CvReplaced = 'cv_replaced';
    case CvDeleted = 'cv_deleted';
    case CandidatePoolGranted = 'candidate_pool_granted';
    case CandidatePoolDeclined = 'candidate_pool_declined';
    case CandidatePoolWithdrawn = 'candidate_pool_withdrawn';
    case CandidatePoolRegranted = 'candidate_pool_regranted';
    case PrivacyRequestSubmitted = 'privacy_request_submitted';
    case PrivacyAccessRequested = 'privacy_access_requested';
    case PrivacyCorrectionRequested = 'privacy_correction_requested';
    case PrivacyRequestCancelled = 'privacy_request_cancelled';
    case PrivacyAccessCompleted = 'privacy_access_completed';
    case PrivacyCorrectionCompleted = 'privacy_correction_completed';
    case PrivacyRequestRejected = 'privacy_request_rejected';
    case PrivacyExportRequested = 'privacy_export_requested';
    case PrivacyExportReady = 'privacy_export_ready';
    case PrivacyExportDownloaded = 'privacy_export_downloaded';
    case PrivacyExportExpired = 'privacy_export_expired';
    case PrivacyExportFailed = 'privacy_export_failed';

    public function category(): string
    {
        return match ($this) {
            self::AccountCreated, self::EmailVerified => 'الحساب',
            self::Login, self::Logout => 'الجلسة',
            self::EmailNotificationsEnabled, self::EmailNotificationsDisabled => 'الإعدادات',
            self::ProfileUpdated => 'الملف الشخصي',
            self::CompetencyUpdated => 'صفحة الكفاءات',
            self::AdminUserUpdated => 'من الإدارة',
            self::ProgramRegistration, self::PathRegistration, self::VolunteerRegistration => 'التسجيل',
            self::AttendanceCheckIn => 'الحضور',
            self::CertificateDownloaded => 'الشهادات',
            self::CvUploaded, self::CvReplaced, self::CvDeleted => 'السيرة الذاتية',
            self::CandidatePoolGranted, self::CandidatePoolDeclined, self::CandidatePoolWithdrawn, self::CandidatePoolRegranted => 'قاعدة المرشحين',
            self::PrivacyRequestSubmitted, self::PrivacyAccessRequested, self::PrivacyCorrectionRequested,
            self::PrivacyRequestCancelled, self::PrivacyAccessCompleted, self::PrivacyCorrectionCompleted,
            self::PrivacyRequestRejected, self::PrivacyExportRequested, self::PrivacyExportReady,
            self::PrivacyExportDownloaded, self::PrivacyExportExpired, self::PrivacyExportFailed => 'الخصوصية',
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::AccountCreated => 'إنشاء حساب',
            self::Login => 'تسجيل دخول',
            self::Logout => 'تسجيل خروج',
            self::EmailVerified => 'تأكيد البريد الإلكتروني',
            self::EmailNotificationsEnabled => 'تفعيل إشعارات البريد',
            self::EmailNotificationsDisabled => 'تعطيل إشعارات البريد',
            self::ProfileUpdated => 'تحديث بيانات',
            self::CompetencyUpdated => 'تحديث صفحة الكفاءات',
            self::AdminUserUpdated => 'تعديل من الإدارة',
            self::ProgramRegistration => 'تسجيل في برنامج',
            self::PathRegistration => 'تسجيل في مسار',
            self::VolunteerRegistration => 'تسجيل في فرصة تطوعية',
            self::AttendanceCheckIn => 'تسجيل حضور',
            self::CertificateDownloaded => 'تحميل شهادة',
            self::CvUploaded => 'رفع سيرة ذاتية',
            self::CvReplaced => 'استبدال سيرة ذاتية',
            self::CvDeleted => 'حذف سيرة ذاتية',
            self::CandidatePoolGranted => 'الانضمام لقاعدة المرشحين',
            self::CandidatePoolDeclined => 'رفض الانضمام لقاعدة المرشحين',
            self::CandidatePoolWithdrawn => 'سحب موافقة قاعدة المرشحين',
            self::CandidatePoolRegranted => 'إعادة الانضمام لقاعدة المرشحين',
            self::PrivacyRequestSubmitted => 'تقديم طلب خصوصية',
            self::PrivacyAccessRequested => 'طلب الوصول إلى البيانات',
            self::PrivacyCorrectionRequested => 'طلب تصحيح البيانات',
            self::PrivacyRequestCancelled => 'إلغاء طلب خصوصية',
            self::PrivacyAccessCompleted => 'اكتمال طلب الوصول',
            self::PrivacyCorrectionCompleted => 'اكتمال طلب التصحيح',
            self::PrivacyRequestRejected => 'رفض طلب خصوصية',
            self::PrivacyExportRequested => 'طلب تصدير البيانات',
            self::PrivacyExportReady => 'جاهزية ملف التصدير',
            self::PrivacyExportDownloaded => 'تنزيل ملف التصدير',
            self::PrivacyExportExpired => 'انتهاء ملف التصدير',
            self::PrivacyExportFailed => 'فشل تصدير البيانات',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function competencySectionLabels(): array
    {
        return [
            'bio' => 'النبذة التعريفية',
            'skills' => 'المهارات',
            'languages' => 'اللغات',
            'office_tools' => 'الأدوات الرقمية',
            'education' => 'التعليم',
            'experience' => 'الخبرات',
            'external_courses' => 'الدورات والشهادات الخارجية',
            'links' => 'الروابط',
            'cv_attachment' => 'ملف السيرة الذاتية',
            'cv_display' => 'إعدادات العرض',
            'visibility' => 'ظهور الأقسام',
        ];
    }
}
