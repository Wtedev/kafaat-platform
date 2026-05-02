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

    case NewsStaffCopy = 'news_staff_copy';
    case LearningPathLaunched = 'learning_path_launched';
    case VolunteerOpportunityPublished = 'volunteer_opportunity_published';
    case StaffNewProgramRegistration = 'staff_new_program_registration';
    case StaffNewPathRegistration = 'staff_new_path_registration';
    case StaffNewVolunteerRegistration = 'staff_new_volunteer_registration';
    case StaffTrainingEntityCreated = 'staff_training_entity_created';
    case StaffVolunteerOpportunityCreated = 'staff_volunteer_opportunity_created';
    case RegistrationWindowOpened = 'registration_window_opened';
    case RegistrationWindowClosed = 'registration_window_closed';
    case TrainingRunStarted = 'training_run_started';
    case TrainingRunEnded = 'training_run_ended';
    case BeneficiaryApprovedProgramStarting = 'beneficiary_approved_program_starting';

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
            self::NewsStaffCopy => 'نشر خبر (نسخة موظفين)',
            self::LearningPathLaunched => 'نشر مسار تعليمي',
            self::VolunteerOpportunityPublished => 'نشر فرصة تطوعية',
            self::StaffNewProgramRegistration => 'تسجيل جديد في برنامج',
            self::StaffNewPathRegistration => 'تسجيل جديد في مسار',
            self::StaffNewVolunteerRegistration => 'تسجيل جديد في تطوع',
            self::StaffTrainingEntityCreated => 'إنشاء برنامج أو مسار',
            self::StaffVolunteerOpportunityCreated => 'إنشاء فرصة تطوعية',
            self::RegistrationWindowOpened => 'بدء فترة التسجيل',
            self::RegistrationWindowClosed => 'انتهاء فترة التسجيل',
            self::TrainingRunStarted => 'بدء البرنامج',
            self::TrainingRunEnded => 'انتهاء البرنامج',
            self::BeneficiaryApprovedProgramStarting => 'بدء برنامج مسجّل فيه',
        };
    }
}
