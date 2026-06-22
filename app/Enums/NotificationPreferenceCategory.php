<?php

namespace App\Enums;

/**
 * فئات تفضيلات التنبيهات المعروضة للمستفيد (واجهة بسيطة بدل 22 نوعاً).
 */
enum NotificationPreferenceCategory: string
{
    case Account = 'account';
    case ProgramsNew = 'programs_new';
    case ProgramsUpdates = 'programs_updates';
    case Reminders = 'reminders';
    case Volunteering = 'volunteering';
    case News = 'news';
    case Announcements = 'announcements';

    public function label(): string
    {
        return match ($this) {
            self::Account => 'تسجيلاتي وشهاداتي',
            self::ProgramsNew => 'برامج ومسارات جديدة',
            self::ProgramsUpdates => 'تحديثات البرامج والتطوع',
            self::Reminders => 'تذكيرات المواعيد',
            self::Volunteering => 'فرص تطوعية جديدة',
            self::News => 'الأخبار',
            self::Announcements => 'رسائل الإدارة',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Account => 'قبول أو رفض تسجيلك، وصدور شهاداتك.',
            self::ProgramsNew => 'عند نشر برنامج تدريبي أو مسار تعليمي جديد.',
            self::ProgramsUpdates => 'عند تعديل برنامج سجّلت فيه أو تحديث فرصة تطوعية.',
            self::Reminders => 'بدء أو انتهاء التسجيل، وبدء البرامج التي أنت مسجّل فيها.',
            self::Volunteering => 'عند نشر فرصة تطوعية جديدة.',
            self::News => 'عند نشر خبر على المنصة.',
            self::Announcements => 'رسائل عامة من فريق كفاءات.',
        };
    }

    /**
     * هل يمكن للمستفيد إيقاف هذه الفئة داخل الموقع؟
     */
    public function canDisableInApp(): bool
    {
        return match ($this) {
            self::Account => false,
            default => true,
        };
    }

    /**
     * هل يمكن إرسال بريد لهذه الفئة أصلاً؟ (معظمها داخل الموقع فقط).
     */
    public function supportsEmail(): bool
    {
        return match ($this) {
            self::Account => true,
            default => false,
        };
    }

    /**
     * @return array{in_app: bool, email: bool}
     */
    public function defaultPreferences(): array
    {
        return match ($this) {
            self::Account => ['in_app' => true, 'email' => false],
            self::ProgramsNew => ['in_app' => true, 'email' => false],
            self::ProgramsUpdates => ['in_app' => false, 'email' => false],
            self::Reminders => ['in_app' => false, 'email' => false],
            self::Volunteering => ['in_app' => true, 'email' => false],
            self::News => ['in_app' => false, 'email' => false],
            self::Announcements => ['in_app' => true, 'email' => false],
        };
    }

    /**
     * @return list<self>
     */
    public static function forBeneficiarySettings(): array
    {
        return [
            self::Account,
            self::ProgramsNew,
            self::ProgramsUpdates,
            self::Reminders,
            self::Volunteering,
            self::News,
            self::Announcements,
        ];
    }
}
