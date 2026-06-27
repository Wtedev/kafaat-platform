<?php

namespace App\Enums;

enum DeletionHandlerName: string
{
    case AuthenticationData = 'authentication_data';
    case CandidatePoolWithdrawal = 'candidate_pool_withdrawal';
    case Notifications = 'notifications';
    case UserDocuments = 'user_documents';
    case PrivacyExports = 'privacy_exports';
    case ActivityLogs = 'activity_logs';
    case RegistrationsRetention = 'registrations_retention';
    case AttendanceRetention = 'attendance_retention';
    case CertificatesRetention = 'certificates_retention';
    case PolicyAcknowledgementsRetention = 'policy_acknowledgements_retention';
    case ConsentEventsRetention = 'consent_events_retention';
    case AuditLogsRetention = 'audit_logs_retention';
    case SecurityLogsRetention = 'security_logs_retention';
    case ProfileAnonymization = 'profile_anonymization';
    case AccountAnonymization = 'account_anonymization';

    /**
     * @return list<self>
     */
    public static function executionOrder(): array
    {
        return [
            self::AuthenticationData,
            self::CandidatePoolWithdrawal,
            self::Notifications,
            self::UserDocuments,
            self::PrivacyExports,
            self::ActivityLogs,
            self::RegistrationsRetention,
            self::AttendanceRetention,
            self::CertificatesRetention,
            self::PolicyAcknowledgementsRetention,
            self::ConsentEventsRetention,
            self::AuditLogsRetention,
            self::SecurityLogsRetention,
            self::ProfileAnonymization,
            self::AccountAnonymization,
        ];
    }
}
