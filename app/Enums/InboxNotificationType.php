<?php

namespace App\Enums;

enum InboxNotificationType: string
{
    case ProgramLaunched = 'program_launched';
    case ProgramUpdated = 'program_updated';
    case NewsPublished = 'news_published';
    case RegistrationApproved = 'registration_approved';
    case RegistrationRejected = 'registration_rejected';
    case CertificateIssued = 'certificate_issued';
    case VolunteerOpportunityUpdated = 'volunteer_opportunity_updated';
    case GeneralMessage = 'general_message';
    case UserAlert = 'user_alert';

    public function arabicLabel(): string
    {
        return match ($this) {
            self::ProgramLaunched => 'إطلاق برنامج',
            self::ProgramUpdated => 'تحديث بخصوص برنامج',
            self::NewsPublished => 'نشر خبر',
            self::RegistrationApproved => 'قبول تسجيل',
            self::RegistrationRejected => 'رفض تسجيل',
            self::CertificateIssued => 'صدور شهادة',
            self::VolunteerOpportunityUpdated => 'تحديث فرصة تطوعية',
            self::GeneralMessage => 'رسالة عامة',
            self::UserAlert => 'تنبيه من جهة',
        };
    }
}
