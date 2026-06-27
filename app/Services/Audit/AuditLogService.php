<?php

namespace App\Services\Audit;

use App\Enums\AuditLogResult;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    public function record(
        ?User $actor,
        string $action,
        AuditLogResult $result = AuditLogResult::Success,
        ?User $targetUser = null,
        ?Model $resource = null,
        ?string $reason = null,
        ?array $metadata = null,
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'actor_type' => $actor !== null ? 'user' : 'system',
            'action' => $action,
            'target_user_id' => $targetUser?->id,
            'resource_type' => $resource !== null ? $resource->getMorphClass() : null,
            'resource_id' => $resource?->getKey(),
            'result' => $result,
            'reason' => $reason,
            'request_id' => $this->resolveRequestId($request),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => AuditMetadataRedactor::redact($metadata),
            'occurred_at' => now(),
        ]);
    }

    private function resolveRequestId(?Request $request): ?string
    {
        if ($request === null) {
            return null;
        }

        $fromAttribute = $request->attributes->get('request_id');

        if (is_string($fromAttribute) && $fromAttribute !== '') {
            return $fromAttribute;
        }

        return null;
    }
}
