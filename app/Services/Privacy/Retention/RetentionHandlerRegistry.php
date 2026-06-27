<?php

namespace App\Services\Privacy\Retention;

use App\Services\Privacy\Retention\Contracts\RetentionResourceHandler;
use App\Services\Privacy\Retention\Handlers\ActivityLogRetentionHandler;
use App\Services\Privacy\Retention\Handlers\AuditLogRetentionHandler;
use App\Services\Privacy\Retention\Handlers\EmailLogRetentionHandler;
use App\Services\Privacy\Retention\Handlers\ExpiredOtpRetentionHandler;
use App\Services\Privacy\Retention\Handlers\ExpiredPasswordResetRetentionHandler;
use App\Services\Privacy\Retention\Handlers\ExpiredSessionRetentionHandler;
use App\Services\Privacy\Retention\Handlers\NotificationRetentionHandler;
use App\Services\Privacy\Retention\Handlers\PrivacyExportRetentionHandler;
use App\Services\Privacy\Retention\Handlers\SecurityLogRetentionHandler;
use InvalidArgumentException;

final class RetentionHandlerRegistry
{
    /**
     * @var array<string, RetentionResourceHandler>
     */
    private array $handlers = [];

    public function __construct(
        RetentionResourceCatalog $catalog,
        PrivacyExportRetentionHandler $privacyExportRetentionHandler,
        ExpiredOtpRetentionHandler $expiredOtpRetentionHandler,
        ExpiredPasswordResetRetentionHandler $expiredPasswordResetRetentionHandler,
        ExpiredSessionRetentionHandler $expiredSessionRetentionHandler,
        NotificationRetentionHandler $notificationRetentionHandler,
        ActivityLogRetentionHandler $activityLogRetentionHandler,
        AuditLogRetentionHandler $auditLogRetentionHandler,
        SecurityLogRetentionHandler $securityLogRetentionHandler,
        EmailLogRetentionHandler $emailLogRetentionHandler,
    ) {
        foreach ([
            $privacyExportRetentionHandler,
            $expiredOtpRetentionHandler,
            $expiredPasswordResetRetentionHandler,
            $expiredSessionRetentionHandler,
            $notificationRetentionHandler,
            $activityLogRetentionHandler,
            $auditLogRetentionHandler,
            $securityLogRetentionHandler,
            $emailLogRetentionHandler,
        ] as $handler) {
            $this->handlers[$handler->resourceType()] = $handler;
        }

        foreach ($catalog->definitions() as $definition) {
            if (! $definition->schedulable) {
                continue;
            }

            if (! isset($this->handlers[$definition->code])) {
                throw new InvalidArgumentException("Missing handler for schedulable resource: {$definition->code}");
            }
        }
    }

    public function forResource(string $resourceType): RetentionResourceHandler
    {
        $handler = $this->handlers[$resourceType] ?? null;

        if ($handler === null) {
            throw new InvalidArgumentException("No retention handler registered for: {$resourceType}");
        }

        return $handler;
    }
}
