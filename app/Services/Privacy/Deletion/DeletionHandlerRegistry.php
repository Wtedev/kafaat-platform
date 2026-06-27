<?php

namespace App\Services\Privacy\Deletion;

use App\Data\Privacy\DeletionExecutionContext;
use App\Enums\DeletionHandlerName;
use App\Services\Privacy\Deletion\Contracts\DeletionHandlerInterface;
use App\Services\Privacy\Deletion\Handlers\AccountAnonymizationHandler;
use App\Services\Privacy\Deletion\Handlers\ActivityLogDeletionHandler;
use App\Services\Privacy\Deletion\Handlers\AttendanceRestrictedRetentionHandler;
use App\Services\Privacy\Deletion\Handlers\AuditLogRestrictedRetentionHandler;
use App\Services\Privacy\Deletion\Handlers\AuthenticationDataDeletionHandler;
use App\Services\Privacy\Deletion\Handlers\CandidatePoolWithdrawalHandler;
use App\Services\Privacy\Deletion\Handlers\CertificateRestrictedRetentionHandler;
use App\Services\Privacy\Deletion\Handlers\ConsentEventsRestrictedRetentionHandler;
use App\Services\Privacy\Deletion\Handlers\NotificationDeletionHandler;
use App\Services\Privacy\Deletion\Handlers\PolicyAcknowledgementsRestrictedRetentionHandler;
use App\Services\Privacy\Deletion\Handlers\PrivacyExportDeletionHandler;
use App\Services\Privacy\Deletion\Handlers\ProfileAnonymizationHandler;
use App\Services\Privacy\Deletion\Handlers\RegistrationRestrictedRetentionHandler;
use App\Services\Privacy\Deletion\Handlers\SecurityLogRestrictedRetentionHandler;
use App\Services\Privacy\Deletion\Handlers\UserDocumentsDeletionHandler;

final class DeletionHandlerRegistry
{
    /**
     * @return array<string, DeletionHandlerInterface>
     */
    public function handlers(): array
    {
        return [
            DeletionHandlerName::AuthenticationData->value => app(AuthenticationDataDeletionHandler::class),
            DeletionHandlerName::CandidatePoolWithdrawal->value => app(CandidatePoolWithdrawalHandler::class),
            DeletionHandlerName::Notifications->value => app(NotificationDeletionHandler::class),
            DeletionHandlerName::UserDocuments->value => app(UserDocumentsDeletionHandler::class),
            DeletionHandlerName::PrivacyExports->value => app(PrivacyExportDeletionHandler::class),
            DeletionHandlerName::ActivityLogs->value => app(ActivityLogDeletionHandler::class),
            DeletionHandlerName::RegistrationsRetention->value => app(RegistrationRestrictedRetentionHandler::class),
            DeletionHandlerName::AttendanceRetention->value => app(AttendanceRestrictedRetentionHandler::class),
            DeletionHandlerName::CertificatesRetention->value => app(CertificateRestrictedRetentionHandler::class),
            DeletionHandlerName::PolicyAcknowledgementsRetention->value => app(PolicyAcknowledgementsRestrictedRetentionHandler::class),
            DeletionHandlerName::ConsentEventsRetention->value => app(ConsentEventsRestrictedRetentionHandler::class),
            DeletionHandlerName::AuditLogsRetention->value => app(AuditLogRestrictedRetentionHandler::class),
            DeletionHandlerName::SecurityLogsRetention->value => app(SecurityLogRestrictedRetentionHandler::class),
            DeletionHandlerName::ProfileAnonymization->value => app(ProfileAnonymizationHandler::class),
            DeletionHandlerName::AccountAnonymization->value => app(AccountAnonymizationHandler::class),
        ];
    }

    public function get(DeletionHandlerName $handler): DeletionHandlerInterface
    {
        $handlers = $this->handlers();

        if (! isset($handlers[$handler->value])) {
            throw new \InvalidArgumentException("Deletion handler [{$handler->value}] is not registered.");
        }

        return $handlers[$handler->value];
    }
}
