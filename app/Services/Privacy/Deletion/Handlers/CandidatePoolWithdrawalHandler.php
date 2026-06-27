<?php

namespace App\Services\Privacy\Deletion\Handlers;

use App\Data\Privacy\DeletionExecutionContext;
use App\Enums\CandidatePoolConsentSource;
use App\Enums\CandidatePoolPreferenceStatus;
use App\Enums\DeletionHandlerName;
use App\Models\CandidatePoolPreference;
use App\Services\CandidatePool\CandidatePoolConsentService;
use App\Services\Privacy\Deletion\Contracts\DeletionHandlerInterface;

final class CandidatePoolWithdrawalHandler implements DeletionHandlerInterface
{
    public function __construct(
        private readonly CandidatePoolConsentService $consentService,
    ) {}

    public function name(): string
    {
        return DeletionHandlerName::CandidatePoolWithdrawal->value;
    }

    public function handle(DeletionExecutionContext $context): void
    {
        $user = $context->target;

        $preference = CandidatePoolPreference::query()->where('user_id', $user->id)->first();

        if ($preference === null) {
            return;
        }

        if ($preference->current_status === CandidatePoolPreferenceStatus::Granted) {
            $this->consentService->withdraw($user, CandidatePoolConsentSource::PrivacySettings, $context->request);
        }

        $preference->forceFill([
            'current_status' => CandidatePoolPreferenceStatus::Withdrawn,
            'decided_at' => now(),
        ])->saveQuietly();
    }
}
