<?php

namespace App\Data\Privacy;

use App\Enums\AccountStatus;
use App\Enums\CandidatePoolPreferenceStatus;
use App\Enums\IdentityType;
use App\Models\PrivacyPolicyAcknowledgement;
use App\Models\PrivacyPolicyVersion;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Services\CandidatePool\CandidatePoolConsentService;
use App\Services\CandidatePool\CandidatePoolConsentVersionService;
use App\Services\Documents\CvDocumentService;
use App\Services\Privacy\PrivacyPolicyAcknowledgementService;
use App\Services\Privacy\PrivacyPolicyService;
use Illuminate\Support\Collection;

final class PortalPrivacyCenterViewData
{
    /**
     * @param  list<array<string, mixed>>  $requests
     */
    public function __construct(
        public readonly array $account,
        public readonly array $policy,
        public readonly array $candidatePool,
        public readonly ?array $cv,
        public readonly array $requests,
        public readonly bool $canSubmitRequests,
    ) {}

    public static function forUser(User $user, CvDocumentService $cvService, CandidatePoolConsentService $consentService): self
    {
        $user->loadMissing(['profile', 'candidatePoolPreference']);

        $activePolicy = PrivacyPolicyService::active();
        $latestAck = PrivacyPolicyAcknowledgement::query()
            ->where('user_id', $user->id)
            ->with('privacyPolicyVersion')
            ->latest('acknowledged_at')
            ->first();

        $preference = $user->candidatePoolPreference;
        $consentVersion = CandidatePoolConsentVersionService::activeVersion();
        $cv = $cvService->currentCv($user);

        $requests = PrivacyRequest::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn (PrivacyRequest $request): array => [
                'uuid' => $request->uuid,
                'type' => $request->request_type->label(),
                'status' => $request->status->label(),
                'submitted_at' => $request->created_at?->translatedFormat('j F Y'),
                'updated_at' => $request->updated_at?->translatedFormat('j F Y'),
                'completed_at' => $request->completed_at?->translatedFormat('j F Y'),
                'user_message' => $request->user_visible_response ?? $request->latestUserVisibleMessage(),
            ])
            ->all();

        return new self(
            account: [
                'full_name' => $user->fullName(),
                'email' => $user->email,
                'phone' => $user->phone,
                'birth_date' => $user->profile?->birth_date?->translatedFormat('j F Y'),
                'identity_type' => $user->identity_type instanceof IdentityType ? $user->identity_type->label() : null,
                'identity_masked' => $user->maskedIdentityNumber(),
                'created_at' => $user->created_at?->translatedFormat('j F Y'),
                'updated_at' => $user->updated_at?->translatedFormat('j F Y'),
                'account_status' => $user->account_status?->label() ?? AccountStatus::Active->label(),
            ],
            policy: [
                'active_version' => $activePolicy?->version,
                'active_url' => $activePolicy ? route('public.privacy') : null,
                'acknowledged_version' => $latestAck?->privacyPolicyVersion?->version,
                'acknowledged_at' => $latestAck?->acknowledged_at?->translatedFormat('j F Y'),
                'needs_reacknowledgement' => app(PrivacyPolicyAcknowledgementService::class)->userNeedsAcknowledgement($user),
                'acknowledged_version_url' => $latestAck?->privacyPolicyVersion instanceof PrivacyPolicyVersion
                    ? route('public.privacy.version', ['version' => $latestAck->privacyPolicyVersion->version])
                    : null,
            ],
            candidatePool: [
                'status' => $preference?->current_status?->value ?? CandidatePoolPreferenceStatus::Undecided->value,
                'status_label' => $preference?->current_status?->label() ?? 'لم يُطلب القرار بعد',
                'last_decision_at' => $preference?->granted_at?->translatedFormat('j F Y')
                    ?? $preference?->declined_at?->translatedFormat('j F Y')
                    ?? $preference?->withdrawn_at?->translatedFormat('j F Y'),
                'consent_version' => $consentVersion?->version,
                'consent_text' => $consentService->consentText(),
                'settings_url' => route('portal.candidate-pool.settings'),
            ],
            cv: $cv === null ? null : [
                'uploaded_at' => $cv->created_at?->translatedFormat('j F Y'),
                'mime' => $cv->mime_type,
                'size_label' => self::formatBytes((int) $cv->size_bytes),
                'updated_at' => $cv->updated_at?->translatedFormat('j F Y'),
                'download_url' => route('portal.competency.cv.download'),
                'delete_url' => route('portal.competency.cv.destroy'),
            ],
            requests: $requests,
            canSubmitRequests: $user->allowsOperationalAccess() && ! $user->isAnonymized(),
        );
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' بايت';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' ك.ب';
        }

        return round($bytes / (1024 * 1024), 1).' م.ب';
    }
}
