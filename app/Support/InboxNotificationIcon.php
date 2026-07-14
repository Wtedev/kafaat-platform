<?php

namespace App\Support;

use App\Enums\InboxNotificationType;

/**
 * Maps in-app notification types to Heroicons (outline) for the beneficiary portal.
 */
final class InboxNotificationIcon
{
    public const FALLBACK = 'heroicon-o-bell';

    public static function heroiconFor(?InboxNotificationType $type): string
    {
        if (! $type instanceof InboxNotificationType) {
            return self::FALLBACK;
        }

        return match ($type) {
            InboxNotificationType::StaffNewProgramRegistration,
            InboxNotificationType::StaffNewPathRegistration,
            InboxNotificationType::StaffNewVolunteerRegistration => 'heroicon-o-user-plus',

            InboxNotificationType::RegistrationApproved,
            InboxNotificationType::BeneficiaryApprovedProgramStarting => 'heroicon-o-check-circle',

            InboxNotificationType::RegistrationRejected => 'heroicon-o-x-circle',

            InboxNotificationType::CertificateIssued => 'heroicon-o-academic-cap',

            InboxNotificationType::VolunteerOpportunityUpdated,
            InboxNotificationType::VolunteerOpportunityPublished,
            InboxNotificationType::StaffVolunteerOpportunityCreated => 'heroicon-o-heart',

            InboxNotificationType::ProgramLaunched,
            InboxNotificationType::ProgramUpdated,
            InboxNotificationType::LearningPathLaunched,
            InboxNotificationType::StaffTrainingEntityCreated => 'heroicon-o-book-open',

            InboxNotificationType::TrainingRunStarted => 'heroicon-o-play-circle',
            InboxNotificationType::TrainingRunEnded => 'heroicon-o-flag',

            InboxNotificationType::RegistrationWindowOpened,
            InboxNotificationType::RegistrationWindowClosed => 'heroicon-o-calendar-days',

            InboxNotificationType::NewsPublished,
            InboxNotificationType::NewsStaffCopy => 'heroicon-o-newspaper',

            InboxNotificationType::GeneralMessage => 'heroicon-o-chat-bubble-left-right',
            InboxNotificationType::UserAlert => 'heroicon-o-exclamation-triangle',
        };
    }
}
