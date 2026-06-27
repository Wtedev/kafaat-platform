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
