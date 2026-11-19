<?php

namespace App\Enums;

/**
 * Everything that happens to a training program, as shown in its activity log.
 */
enum ProgramActivityAction: string
{
    case Created = 'created';
    case SettingsUpdated = 'settings_updated';
    case Published = 'published';
    case Unpublished = 'unpublished';
    case Archived = 'archived';
    case Restored = 'restored';
    case Deleted = 'deleted';
    case Undeleted = 'undeleted';
    case RegistrationWindowClosed = 'registration_window_closed';
    case RegistrationWindowReopened = 'registration_window_reopened';
    case RegistrationCreated = 'registration_created';
    case RegistrationApproved = 'registration_approved';
    case RegistrationRejected = 'registration_rejected';
    case RegistrationCancelled = 'registration_cancelled';
    case RegistrationReopened = 'registration_reopened';
    case AttendanceMarked = 'attendance_marked';
    case AttendanceCleared = 'attendance_cleared';
    case SurveyCompleted = 'survey_completed';
    case SurveysCreated = 'surveys_created';
    case SurveyUpdated = 'survey_updated';
    case CertificateIssued = 'certificate_issued';
    case BroadcastCreated = 'broadcast_created';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'إنشاء البرنامج',
            self::SettingsUpdated => 'تعديل الإعدادات',
            self::Published => 'نشر البرنامج',
            self::Unpublished => 'إرجاع إلى مسودة',
            self::Archived => 'أرشفة البرنامج',
            self::Restored => 'استعادة من الأرشيف',
            self::Deleted => 'نقل إلى المحذوفات',
            self::Undeleted => 'استعادة من المحذوفات',
            self::RegistrationWindowClosed => 'إغلاق التسجيل',
            self::RegistrationWindowReopened => 'إعادة فتح التسجيل',
            self::RegistrationCreated => 'تسجيل جديد',
            self::RegistrationApproved => 'قبول تسجيل',
            self::RegistrationRejected => 'رفض تسجيل',
            self::RegistrationCancelled => 'إلغاء تسجيل',
            self::RegistrationReopened => 'إعادة فتح طلب',
            self::AttendanceMarked => 'تحضير',
            self::AttendanceCleared => 'إلغاء تحضير',
            self::SurveyCompleted => 'تعبئة استبيان',
            self::SurveysCreated => 'إنشاء الاستبيانات',
            self::SurveyUpdated => 'تعديل استبيان',
            self::CertificateIssued => 'إصدار شهادة',
            self::BroadcastCreated => 'رسالة جماعية',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::Created, self::SettingsUpdated, self::Published, self::Unpublished, self::Archived, self::Restored, self::Deleted, self::Undeleted,
            self::RegistrationWindowClosed, self::RegistrationWindowReopened => 'program',
            self::RegistrationCreated, self::RegistrationApproved, self::RegistrationRejected, self::RegistrationCancelled, self::RegistrationReopened => 'registrations',
            self::AttendanceMarked, self::AttendanceCleared => 'attendance',
            self::SurveyCompleted, self::SurveysCreated, self::SurveyUpdated => 'surveys',
            self::CertificateIssued => 'certificates',
            self::BroadcastCreated => 'broadcasts',
        };
    }

    /**
     * Lucide icon name for the activity timeline.
     */
    public function icon(): string
    {
        return match ($this->group()) {
            'program' => 'settings-2',
            'registrations' => 'user-check',
            'attendance' => 'scan-line',
            'surveys' => 'clipboard-list',
            'certificates' => 'award',
            default => 'send',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function groupOptions(): array
    {
        return [
            'program' => 'البرنامج',
            'registrations' => 'التسجيلات',
            'attendance' => 'التحضير',
            'surveys' => 'الاستبيانات',
            'certificates' => 'الشهادات',
            'broadcasts' => 'الرسائل',
        ];
    }

    /**
     * @return list<string>
     */
    public static function valuesInGroup(string $group): array
    {
        return array_values(array_map(
            static fn (self $case): string => $case->value,
            array_filter(self::cases(), static fn (self $case): bool => $case->group() === $group),
        ));
    }
}
