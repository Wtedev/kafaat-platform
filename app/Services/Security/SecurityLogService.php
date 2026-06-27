<?php

namespace App\Services\Security;

use App\Enums\SecurityLogResult;
use App\Enums\SecurityLogSeverity;
use App\Models\SecurityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class SecurityLogService
{
    public function record(
        string $event,
        SecurityLogResult $result,
        SecurityLogSeverity $severity = SecurityLogSeverity::Info,
        ?User $user = null,
        ?string $identifier = null,
        ?array $metadata = null,
        ?Request $request = null,
    ): SecurityLog {
        return SecurityLog::query()->create([
            'user_id' => $user?->id,
            'event' => $event,
            'result' => $result,
            'severity' => $severity,
            'request_id' => $this->resolveRequestId($request),
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 512) : null,
            'identifier_hash' => $identifier !== null ? self::hashIdentifier($identifier) : null,
            'metadata' => SensitiveDataRedactor::redact($metadata),
            'occurred_at' => now(),
        ]);
    }

    public static function hashIdentifier(string $identifier): string
    {
        $normalized = strtolower(trim($identifier));
        $key = (string) Config::get('app.key');

        return hash_hmac('sha256', $normalized, 'security-log:'.$key);
    }

    private function resolveRequestId(?Request $request): ?string
    {
        if ($request === null) {
            return null;
        }

        $fromAttribute = $request->attributes->get('request_id');

        return is_string($fromAttribute) && $fromAttribute !== '' ? $fromAttribute : null;
    }
}
