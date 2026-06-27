<?php

namespace App\Services\Privacy\Retention;

use App\Data\Privacy\Retention\RetentionResourceDefinition;
use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionTriggerEvent;
use App\Services\Privacy\Retention\Handlers\ActivityLogRetentionHandler;
use App\Services\Privacy\Retention\Handlers\AuditLogRetentionHandler;
use App\Services\Privacy\Retention\Handlers\EmailLogRetentionHandler;
use App\Services\Privacy\Retention\Handlers\ExpiredOtpRetentionHandler;
use App\Services\Privacy\Retention\Handlers\ExpiredPasswordResetRetentionHandler;
use App\Services\Privacy\Retention\Handlers\ExpiredSessionRetentionHandler;
use App\Services\Privacy\Retention\Handlers\NotificationRetentionHandler;
use App\Services\Privacy\Retention\Handlers\PrivacyExportRetentionHandler;
use App\Services\Privacy\Retention\Handlers\RetainRestrictedHandler;
use App\Services\Privacy\Retention\Handlers\SecurityLogRetentionHandler;
use InvalidArgumentException;

final class RetentionResourceCatalog
{
    /**
     * @return array<string, RetentionResourceDefinition>
     */
    public function definitions(): array
    {
        return [
            'privacy_export_files' => new RetentionResourceDefinition(
                code: 'privacy_export_files',
                label: 'ملفات تصدير الخصوصية',
                handlerClass: PrivacyExportRetentionHandler::class,
                supportedTriggers: [RetentionTriggerEvent::ExpiredAt],
                supportedActions: [RetentionPolicyAction::Delete],
                hasFiles: true,
                allowsDelete: true,
                allowsAnonymize: false,
                requiresManualApproval: false,
                needsBackupRunbook: true,
                allowsLegalHold: true,
                schedulable: true,
            ),
            'email_verification_codes' => new RetentionResourceDefinition(
                code: 'email_verification_codes',
                label: 'رموز التحقق بالبريد',
                handlerClass: ExpiredOtpRetentionHandler::class,
                supportedTriggers: [RetentionTriggerEvent::ExpiredAt],
                supportedActions: [RetentionPolicyAction::Delete],
                hasFiles: false,
                allowsDelete: true,
                allowsAnonymize: false,
                requiresManualApproval: false,
                needsBackupRunbook: false,
                allowsLegalHold: false,
                schedulable: true,
            ),
            'password_reset_tokens' => new RetentionResourceDefinition(
                code: 'password_reset_tokens',
                label: 'رموز استعادة كلمة المرور',
                handlerClass: ExpiredPasswordResetRetentionHandler::class,
                supportedTriggers: [RetentionTriggerEvent::CreatedAt],
                supportedActions: [RetentionPolicyAction::Delete],
                hasFiles: false,
                allowsDelete: true,
                allowsAnonymize: false,
                requiresManualApproval: false,
                needsBackupRunbook: false,
                allowsLegalHold: false,
                schedulable: true,
            ),
            'sessions' => new RetentionResourceDefinition(
                code: 'sessions',
                label: 'الجلسات',
                handlerClass: ExpiredSessionRetentionHandler::class,
                supportedTriggers: [RetentionTriggerEvent::LastActivityAt],
                supportedActions: [RetentionPolicyAction::Delete],
                hasFiles: false,
                allowsDelete: true,
                allowsAnonymize: false,
                requiresManualApproval: false,
                needsBackupRunbook: false,
                allowsLegalHold: false,
                schedulable: true,
            ),
            'in_app_notifications' => new RetentionResourceDefinition(
                code: 'in_app_notifications',
                label: 'إشعارات البوابة',
                handlerClass: NotificationRetentionHandler::class,
                supportedTriggers: [RetentionTriggerEvent::CreatedAt],
                supportedActions: [RetentionPolicyAction::Delete],
                hasFiles: false,
                allowsDelete: true,
                allowsAnonymize: false,
                requiresManualApproval: false,
                needsBackupRunbook: false,
                allowsLegalHold: true,
                schedulable: true,
            ),
            'user_activity_logs' => new RetentionResourceDefinition(
                code: 'user_activity_logs',
                label: 'سجل نشاط المستفيد',
                handlerClass: ActivityLogRetentionHandler::class,
                supportedTriggers: [RetentionTriggerEvent::CreatedAt, RetentionTriggerEvent::AccountAnonymizedAt, RetentionTriggerEvent::AccountDeletedAt],
                supportedActions: [RetentionPolicyAction::Delete, RetentionPolicyAction::Anonymize],
                hasFiles: false,
                allowsDelete: true,
                allowsAnonymize: true,
                requiresManualApproval: false,
                needsBackupRunbook: false,
                allowsLegalHold: true,
                schedulable: true,
            ),
            'audit_logs' => new RetentionResourceDefinition(
                code: 'audit_logs',
                label: 'سجل التدقيق',
                handlerClass: AuditLogRetentionHandler::class,
                supportedTriggers: [RetentionTriggerEvent::CreatedAt],
                supportedActions: [RetentionPolicyAction::Delete, RetentionPolicyAction::Anonymize],
                hasFiles: false,
                allowsDelete: true,
                allowsAnonymize: true,
                requiresManualApproval: true,
                needsBackupRunbook: true,
                allowsLegalHold: true,
                schedulable: true,
            ),
            'security_logs' => new RetentionResourceDefinition(
                code: 'security_logs',
                label: 'سجل الأحداث الأمنية',
                handlerClass: SecurityLogRetentionHandler::class,
                supportedTriggers: [RetentionTriggerEvent::CreatedAt],
                supportedActions: [RetentionPolicyAction::Delete, RetentionPolicyAction::Anonymize],
                hasFiles: false,
                allowsDelete: true,
                allowsAnonymize: true,
                requiresManualApproval: true,
                needsBackupRunbook: true,
                allowsLegalHold: true,
                schedulable: true,
            ),
            'email_logs' => new RetentionResourceDefinition(
                code: 'email_logs',
                label: 'سجل البريد',
                handlerClass: EmailLogRetentionHandler::class,
                supportedTriggers: [RetentionTriggerEvent::CreatedAt],
                supportedActions: [RetentionPolicyAction::Delete, RetentionPolicyAction::Anonymize],
                hasFiles: false,
                allowsDelete: true,
                allowsAnonymize: true,
                requiresManualApproval: false,
                needsBackupRunbook: false,
                allowsLegalHold: true,
                schedulable: true,
            ),
            'certificates' => new RetentionResourceDefinition(
                code: 'certificates',
                label: 'الشهادات',
                handlerClass: RetainRestrictedHandler::class,
                supportedTriggers: [RetentionTriggerEvent::AccountAnonymizedAt, RetentionTriggerEvent::AccountDeletedAt],
                supportedActions: [RetentionPolicyAction::RetainRestricted],
                hasFiles: false,
                allowsDelete: false,
                allowsAnonymize: false,
                requiresManualApproval: true,
                needsBackupRunbook: true,
                allowsLegalHold: true,
                schedulable: false,
            ),
            'attendance_records' => new RetentionResourceDefinition(
                code: 'attendance_records',
                label: 'سجلات الحضور',
                handlerClass: RetainRestrictedHandler::class,
                supportedTriggers: [RetentionTriggerEvent::AccountAnonymizedAt, RetentionTriggerEvent::AccountDeletedAt],
                supportedActions: [RetentionPolicyAction::RetainRestricted],
                hasFiles: false,
                allowsDelete: false,
                allowsAnonymize: false,
                requiresManualApproval: true,
                needsBackupRunbook: true,
                allowsLegalHold: true,
                schedulable: false,
            ),
            'program_registrations' => new RetentionResourceDefinition(
                code: 'program_registrations',
                label: 'تسجيلات البرامج',
                handlerClass: RetainRestrictedHandler::class,
                supportedTriggers: [RetentionTriggerEvent::AccountAnonymizedAt, RetentionTriggerEvent::AccountDeletedAt],
                supportedActions: [RetentionPolicyAction::RetainRestricted],
                hasFiles: false,
                allowsDelete: false,
                allowsAnonymize: false,
                requiresManualApproval: true,
                needsBackupRunbook: true,
                allowsLegalHold: true,
                schedulable: false,
            ),
            'path_registrations' => new RetentionResourceDefinition(
                code: 'path_registrations',
                label: 'تسجيلات المسارات',
                handlerClass: RetainRestrictedHandler::class,
                supportedTriggers: [RetentionTriggerEvent::AccountAnonymizedAt, RetentionTriggerEvent::AccountDeletedAt],
                supportedActions: [RetentionPolicyAction::RetainRestricted],
                hasFiles: false,
                allowsDelete: false,
                allowsAnonymize: false,
                requiresManualApproval: true,
                needsBackupRunbook: true,
                allowsLegalHold: true,
                schedulable: false,
            ),
            'volunteer_registrations' => new RetentionResourceDefinition(
                code: 'volunteer_registrations',
                label: 'تسجيلات التطوع',
                handlerClass: RetainRestrictedHandler::class,
                supportedTriggers: [RetentionTriggerEvent::AccountAnonymizedAt, RetentionTriggerEvent::AccountDeletedAt],
                supportedActions: [RetentionPolicyAction::RetainRestricted],
                hasFiles: false,
                allowsDelete: false,
                allowsAnonymize: false,
                requiresManualApproval: true,
                needsBackupRunbook: true,
                allowsLegalHold: true,
                schedulable: false,
            ),
            'candidate_pool_consent_events' => new RetentionResourceDefinition(
                code: 'candidate_pool_consent_events',
                label: 'أحداث موافقة قاعدة المرشحين',
                handlerClass: RetainRestrictedHandler::class,
                supportedTriggers: [RetentionTriggerEvent::ConsentWithdrawnAt, RetentionTriggerEvent::AccountAnonymizedAt],
                supportedActions: [RetentionPolicyAction::RetainRestricted],
                hasFiles: false,
                allowsDelete: false,
                allowsAnonymize: false,
                requiresManualApproval: true,
                needsBackupRunbook: true,
                allowsLegalHold: true,
                schedulable: false,
            ),
            'privacy_policy_acknowledgements' => new RetentionResourceDefinition(
                code: 'privacy_policy_acknowledgements',
                label: 'إقرارات سياسة الخصوصية',
                handlerClass: RetainRestrictedHandler::class,
                supportedTriggers: [RetentionTriggerEvent::CreatedAt, RetentionTriggerEvent::AccountAnonymizedAt],
                supportedActions: [RetentionPolicyAction::RetainRestricted],
                hasFiles: false,
                allowsDelete: false,
                allowsAnonymize: false,
                requiresManualApproval: true,
                needsBackupRunbook: true,
                allowsLegalHold: true,
                schedulable: false,
            ),
        ];
    }

    public function get(string $code): RetentionResourceDefinition
    {
        $definition = $this->definitions()[$code] ?? null;

        if ($definition === null) {
            throw new InvalidArgumentException("Unknown retention resource: {$code}");
        }

        return $definition;
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_keys($this->definitions());
    }

    /**
     * @return list<string>
     */
    public function schedulableCodes(): array
    {
        return array_values(array_map(
            fn (RetentionResourceDefinition $definition): string => $definition->code,
            array_filter($this->definitions(), fn (RetentionResourceDefinition $definition): bool => $definition->schedulable),
        ));
    }
}
