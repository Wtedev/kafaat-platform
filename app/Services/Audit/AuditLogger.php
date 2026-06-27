<?php

namespace App\Services\Audit;

use App\Enums\AuditLogResult;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use RuntimeException;

class AuditLogger
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function record(
        ?User $actor,
        string $action,
        AuditLogResult $result = AuditLogResult::Success,
        ?User $targetUser = null,
        ?Model $resource = null,
        ?string $reason = null,
        ?string $reasonCode = null,
        ?array $metadata = null,
        ?Request $request = null,
    ): AuditLog {
        return $this->auditLogService->record(
            $actor,
            $action,
            $result,
            $targetUser,
            $resource,
            $reason ?? $reasonCode,
            $metadata,
            $request,
        );
    }

    public function recordOrFail(
        ?User $actor,
        string $action,
        AuditLogResult $result = AuditLogResult::Success,
        ?User $targetUser = null,
        ?Model $resource = null,
        ?string $reason = null,
        ?string $reasonCode = null,
        ?array $metadata = null,
        ?Request $request = null,
    ): AuditLog {
        try {
            return $this->record(
                $actor,
                $action,
                $result,
                $targetUser,
                $resource,
                $reason,
                $reasonCode,
                $metadata,
                $request,
            );
        } catch (\Throwable $exception) {
            throw new RuntimeException('Unable to persist audit log for sensitive operation.', 0, $exception);
        }
    }
}
