<?php

namespace App\Services\Privacy\Retention\Handlers;

use App\Enums\RetentionPolicyAction;
use App\Enums\RetentionTriggerEvent;
use App\Models\EmailVerificationCode;
use App\Models\RetentionPolicy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class ExpiredOtpRetentionHandler extends AbstractRetentionHandler
{
    public function resourceType(): string
    {
        return 'email_verification_codes';
    }

    public function supportedActions(): array
    {
        return [RetentionPolicyAction::Delete];
    }

    public function supportedTriggers(): array
    {
        return [RetentionTriggerEvent::ExpiredAt];
    }

    public function eligibleQuery(RetentionPolicy $policy, CarbonInterface $cutoff): Builder
    {
        $graceCutoff = $cutoff->copy()->subDays((int) config('privacy_retention.otp_grace_period_days', 1));

        return EmailVerificationCode::query()
            ->where('expires_at', '<=', $graceCutoff);
    }

    public function sourceId(object $record): ?int
    {
        return $record instanceof EmailVerificationCode ? $record->id : null;
    }
}
