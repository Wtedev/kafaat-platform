<?php

namespace App\Services\CandidatePool;

use App\Enums\AuditLogResult;
use App\Enums\CandidatePoolConsentEventType;
use App\Enums\CandidatePoolConsentSource;
use App\Enums\CandidatePoolPreferenceStatus;
use App\Enums\UserActivityAction;
use App\Models\CandidatePoolConsentEvent;
use App\Models\CandidatePoolConsentVersion;
use App\Models\CandidatePoolPreference;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Privacy\PrivacyPolicyContentHasher;
use App\Services\UserActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CandidatePoolConsentService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function consentText(): string
    {
        return (string) config('candidate_pool.consent_checkbox_intro');
    }

    public function shouldPrompt(User $user): bool
    {
        $version = CandidatePoolConsentVersionService::activeVersion();

        if ($version === null || ! $user->hasCompletedRequiredIdentityData()) {
            return false;
        }

        $preference = $this->preferenceFor($user);

        if ($preference->current_status === CandidatePoolPreferenceStatus::Granted) {
            if ($preference->current_consent_version_id === $version->id) {
                return false;
            }

            if ($version->requires_reconsent && $preference->prompted_at !== null && $preference->current_consent_version_id === $version->id) {
                return false;
            }

            if (! $version->requires_reconsent) {
                return false;
            }

            return $preference->prompted_at === null || $preference->current_consent_version_id !== $version->id;
        }

        if ($preference->current_status === CandidatePoolPreferenceStatus::Declined
            && $preference->current_consent_version_id === $version->id
            && $preference->prompted_at !== null) {
            return false;
        }

        if ($preference->prompted_at !== null
            && $preference->current_consent_version_id === $version->id) {
            return false;
        }

        return true;
    }

    public function recordPrompted(User $user, CandidatePoolConsentVersion $version, CandidatePoolConsentSource $source, ?Request $request = null): void
    {
        if (! $this->shouldPrompt($user)) {
            return;
        }

        DB::transaction(function () use ($user, $version, $source, $request): void {
            $preference = $this->preferenceFor($user);
            if ($preference->prompted_at !== null && $preference->current_consent_version_id === $version->id) {
                return;
            }

            $event = $this->createEvent($user, $version, CandidatePoolConsentEventType::Prompted, $source, $request);

            $preference->forceFill([
                'current_consent_version_id' => $version->id,
                'prompted_at' => now(),
                'latest_event_id' => $event->id,
            ])->save();

            $this->auditLog->record($user, 'candidate_pool.prompted', AuditLogResult::Success, $user, $event, request: $request);
        });
    }

    public function grant(User $user, CandidatePoolConsentVersion $version, CandidatePoolConsentSource $source, ?Request $request = null): void
    {
        $this->transition($user, $version, CandidatePoolConsentEventType::Granted, CandidatePoolPreferenceStatus::Granted, $source, UserActivityAction::CandidatePoolGranted, 'candidate_pool.granted', 'انضممت إلى قاعدة المرشحين.', $request);
    }

    public function decline(User $user, CandidatePoolConsentVersion $version, CandidatePoolConsentSource $source, ?Request $request = null): void
    {
        $this->transition($user, $version, CandidatePoolConsentEventType::Declined, CandidatePoolPreferenceStatus::Declined, $source, UserActivityAction::CandidatePoolDeclined, 'candidate_pool.declined', 'اخترت عدم الانضمام إلى قاعدة المرشحين.', $request);
    }

    public function withdraw(User $user, CandidatePoolConsentSource $source, ?Request $request = null): void
    {
        $version = CandidatePoolConsentVersionService::activeVersion();
        if ($version === null) {
            return;
        }

        $this->transition($user, $version, CandidatePoolConsentEventType::Withdrawn, CandidatePoolPreferenceStatus::Withdrawn, $source, UserActivityAction::CandidatePoolWithdrawn, 'candidate_pool.withdrawn', 'سحبت موافقتك على الانضمام إلى قاعدة المرشحين.', $request);
    }

    public function regrant(User $user, CandidatePoolConsentVersion $version, CandidatePoolConsentSource $source, ?Request $request = null): void
    {
        $this->transition($user, $version, CandidatePoolConsentEventType::Regranted, CandidatePoolPreferenceStatus::Granted, $source, UserActivityAction::CandidatePoolRegranted, 'candidate_pool.regranted', 'أعدت الانضمام إلى قاعدة المرشحين.', $request);
    }

    public function isEligibleForPool(User $user): bool
    {
        return app(CandidatePoolQuery::class)->eligibleQuery()
            ->where('users.id', $user->id)
            ->exists();
    }

    private function preferenceFor(User $user): CandidatePoolPreference
    {
        return CandidatePoolPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['current_status' => CandidatePoolPreferenceStatus::Undecided],
        );
    }

    private function transition(
        User $user,
        CandidatePoolConsentVersion $version,
        CandidatePoolConsentEventType $eventType,
        CandidatePoolPreferenceStatus $status,
        CandidatePoolConsentSource $source,
        UserActivityAction $activity,
        string $auditAction,
        string $activityDetail,
        ?Request $request,
    ): void {
        if (! $version->isActive()) {
            throw new \RuntimeException('Consent version is not active.');
        }

        DB::transaction(function () use ($user, $version, $eventType, $status, $source, $activity, $auditAction, $activityDetail, $request): void {
            $event = $this->createEvent($user, $version, $eventType, $source, $request);
            $preference = $this->preferenceFor($user);

            $preference->forceFill([
                'current_status' => $status,
                'current_consent_version_id' => $version->id,
                'decided_at' => now(),
                'latest_event_id' => $event->id,
            ])->save();

            UserActivityLogger::log($user, $activity, $activityDetail);
            $this->auditLog->record($user, $auditAction, AuditLogResult::Success, $user, $event, request: $request);
        });
    }

    private function createEvent(
        User $user,
        CandidatePoolConsentVersion $version,
        CandidatePoolConsentEventType $type,
        CandidatePoolConsentSource $source,
        ?Request $request,
    ): CandidatePoolConsentEvent {
        return CandidatePoolConsentEvent::query()->create([
            'user_id' => $user->id,
            'candidate_pool_consent_version_id' => $version->id,
            'event_type' => $type,
            'consent_text_snapshot' => $this->consentText(),
            'consent_content_hash' => $version->content_hash,
            'source' => $source,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'occurred_at' => now(),
        ]);
    }
}
