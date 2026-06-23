<?php

namespace App\Services\Inbox;

use App\Enums\InboxNotificationType;
use App\Enums\NotificationPreferenceCategory;

/**
 * ربط أنواع التنبيهات بفئات تفضيلات المستفيد وقواعد البريد.
 */
final class NotificationPreferenceCatalog
{
    /**
     * أنواع لا تخضع لتفضيلات المستفيد (تنبيهات عمل الموظفين).
     *
     * @return list<InboxNotificationType>
     */
    public static function staffOnlyTypes(): array
    {
        return [
            InboxNotificationType::NewsStaffCopy,
            InboxNotificationType::StaffNewProgramRegistration,
            InboxNotificationType::StaffNewPathRegistration,
            InboxNotificationType::StaffNewVolunteerRegistration,
            InboxNotificationType::StaffTrainingEntityCreated,
            InboxNotificationType::StaffVolunteerOpportunityCreated,
        ];
    }

    public static function isStaffOnly(InboxNotificationType $type): bool
    {
        return in_array($type, self::staffOnlyTypes(), true);
    }

    public static function categoryFor(InboxNotificationType $type): ?NotificationPreferenceCategory
    {
        return match ($type) {
            InboxNotificationType::RegistrationApproved,
            InboxNotificationType::RegistrationRejected,
            InboxNotificationType::CertificateIssued => NotificationPreferenceCategory::Account,

            InboxNotificationType::ProgramLaunched,
            InboxNotificationType::LearningPathLaunched => NotificationPreferenceCategory::ProgramsNew,

            InboxNotificationType::ProgramUpdated,
            InboxNotificationType::VolunteerOpportunityUpdated => NotificationPreferenceCategory::ProgramsUpdates,

            InboxNotificationType::RegistrationWindowOpened,
            InboxNotificationType::RegistrationWindowClosed,
            InboxNotificationType::BeneficiaryApprovedProgramStarting => NotificationPreferenceCategory::Reminders,

            InboxNotificationType::VolunteerOpportunityPublished => NotificationPreferenceCategory::Volunteering,

            InboxNotificationType::NewsPublished => NotificationPreferenceCategory::News,

            InboxNotificationType::GeneralMessage,
            InboxNotificationType::UserAlert => NotificationPreferenceCategory::Announcements,

            default => null,
        };
    }

    /**
     * هل يُسمح بإرسال بريد عام (InboxNotificationEmail) لهذا النوع؟
     * معظم التنبيهات داخل الموقع فقط؛ القبول/الرفض لهما بريد مخصّص منفصل.
     */
    public static function systemAllowsEmail(InboxNotificationType $type): bool
    {
        return match ($type) {
            InboxNotificationType::CertificateIssued,
            InboxNotificationType::ProgramLaunched,
            InboxNotificationType::LearningPathLaunched,
            InboxNotificationType::NewsPublished,
            InboxNotificationType::VolunteerOpportunityPublished => true,
            default => false,
        };
    }
}
