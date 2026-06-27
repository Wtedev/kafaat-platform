<?php

namespace App\Services\Privacy;

use App\Enums\AccountStatus;
use App\Enums\AuditLogResult;
use App\Enums\PrivacyRequestEventType;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacyRequestType;
use App\Models\PrivacyRequest;
use App\Models\PrivacyRequestEvent;
use App\Models\User;
use App\Services\Access\SensitiveAccessVerification;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class PrivacyRequestService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function submitAccountDeletion(User $user, ?string $reason, Request $request): PrivacyRequest
    {
        if ($user->account_status === AccountStatus::Anonymized) {
            throw new InvalidArgumentException('This account has already been anonymized.');
        }

        if ($this->hasActiveRequest($user, PrivacyRequestType::AccountDeletion)) {
            throw new InvalidArgumentException('An active account deletion request already exists.');
        }

        return DB::transaction(function () use ($user, $reason, $request): PrivacyRequest {
            $privacyRequest = PrivacyRequest::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'request_type' => PrivacyRequestType::AccountDeletion,
                'status' => PrivacyRequestStatus::IdentityVerificationRequired,
                'request_details' => filled($reason) ? ['reason' => Str::limit($reason, 500)] : null,
            ]);

            $user->forceFill([
                'account_status' => AccountStatus::DeletionPending,
                'deletion_request_id' => $privacyRequest->id,
            ])->save();

            $this->recordEvent(
                $privacyRequest,
                PrivacyRequestEventType::Submitted,
                $user,
                PrivacyRequestStatus::IdentityVerificationRequired,
                userVisibleMessage: 'استلمنا طلب حذف حسابك. يلزم إعادة التحقق قبل متابعة المراجعة.',
            );

            $this->auditLogger->recordOrFail(
                $user,
                'account_deletion.requested',
                AuditLogResult::Success,
                $user,
                metadata: ['privacy_request_uuid' => $privacyRequest->uuid],
                request: $request,
            );

            return $privacyRequest;
        });
    }

    public function verifyIdentityWithPassword(PrivacyRequest $privacyRequest, User $user, string $password, Request $request): PrivacyRequest
    {
        $this->assertOwner($privacyRequest, $user);

        if ($privacyRequest->status !== PrivacyRequestStatus::IdentityVerificationRequired) {
            throw new InvalidArgumentException('Identity verification is not required for this request.');
        }

        if (! Hash::check($password, (string) $user->password)) {
            app(\App\Services\Security\SecurityLogService::class)->record(
                'account_deletion.verification_failed',
                \App\Enums\SecurityLogResult::Failed,
                \App\Enums\SecurityLogSeverity::Warning,
                $user,
                metadata: ['privacy_request_uuid' => $privacyRequest->uuid],
                request: $request,
            );

            throw ValidationException::withMessages([
                'password' => 'كلمة المرور غير صحيحة.',
            ]);
        }

        SensitiveAccessVerification::markVerified($request);

        return $this->transition(
            $privacyRequest,
            PrivacyRequestStatus::Submitted,
            PrivacyRequestEventType::IdentityVerified,
            $user,
            identityVerificationMethod: 'password',
            userVisibleMessage: 'تم التحقق من هويتك. سيراجع فريق الخصوصية طلبك.',
            request: $request,
        );
    }

    public function assign(PrivacyRequest $privacyRequest, User $actor, User $assignee): PrivacyRequest
    {
        $this->assertStaff($actor, 'privacy_requests.assign');

        $privacyRequest->forceFill(['assigned_to' => $assignee->id])->save();

        $this->recordEvent(
            $privacyRequest,
            PrivacyRequestEventType::Assigned,
            $actor,
            $privacyRequest->status,
            internalComment: 'Assigned to officer '.$assignee->id,
        );

        $this->auditLogger->recordOrFail(
            $actor,
            'privacy_request.assigned',
            AuditLogResult::Success,
            $privacyRequest->user,
            metadata: ['privacy_request_uuid' => $privacyRequest->uuid],
        );

        return $privacyRequest->fresh();
    }

    public function startReview(PrivacyRequest $privacyRequest, User $actor): PrivacyRequest
    {
        $this->assertStaff($actor, 'privacy_requests.review');

        return $this->transition(
            $privacyRequest,
            PrivacyRequestStatus::UnderReview,
            PrivacyRequestEventType::ReviewStarted,
            $actor,
        );
    }

    public function approve(PrivacyRequest $privacyRequest, User $actor, ?string $summary = null): PrivacyRequest
    {
        $this->assertStaff($actor, 'privacy_requests.approve');

        $privacyRequest->forceFill(['decision_summary' => $summary])->save();

        return $this->transition(
            $privacyRequest,
            PrivacyRequestStatus::Approved,
            PrivacyRequestEventType::Approved,
            $actor,
            userVisibleMessage: 'تمت الموافقة على طلبك. سيبدأ التنفيذ بعد اعتماد خطة الحذف.',
        );
    }

    public function reject(PrivacyRequest $privacyRequest, User $actor, string $reasonCode, string $reason): PrivacyRequest
    {
        $this->assertStaff($actor, 'privacy_requests.reject');

        $privacyRequest->forceFill([
            'rejection_reason_code' => $reasonCode,
            'rejection_reason' => $reason,
        ])->save();

        $result = $this->transition(
            $privacyRequest,
            PrivacyRequestStatus::Rejected,
            PrivacyRequestEventType::Rejected,
            $actor,
            userVisibleMessage: 'تم رفض طلب حذف حسابك.',
        );

        $privacyRequest->user->forceFill([
            'account_status' => AccountStatus::Active,
            'is_active' => true,
            'deletion_request_id' => null,
        ])->save();

        return $result;
    }

    public function cancel(PrivacyRequest $privacyRequest, User $actor): PrivacyRequest
    {
        if ($privacyRequest->user_id !== $actor->id && ! $actor->can('privacy_requests.reject')) {
            throw new AuthorizationException('You cannot cancel this request.');
        }

        if (in_array($privacyRequest->status, [PrivacyRequestStatus::Processing, PrivacyRequestStatus::Completed], true)) {
            throw new InvalidArgumentException('This request can no longer be cancelled.');
        }

        $result = $this->transition(
            $privacyRequest,
            PrivacyRequestStatus::Cancelled,
            PrivacyRequestEventType::Cancelled,
            $actor,
            userVisibleMessage: 'تم إلغاء طلب حذف حسابك.',
        );

        $privacyRequest->user->forceFill([
            'account_status' => AccountStatus::Active,
            'is_active' => true,
            'deletion_request_id' => null,
        ])->save();

        return $result;
    }

    public function markProcessing(PrivacyRequest $privacyRequest, User $actor): PrivacyRequest
    {
        $this->assertStaff($actor, 'privacy_requests.execute');

        $privacyRequest->user->forceFill([
            'account_status' => AccountStatus::DeletionProcessing,
        ])->save();

        return $this->transition(
            $privacyRequest,
            PrivacyRequestStatus::Processing,
            PrivacyRequestEventType::ProcessingStarted,
            $actor,
        );
    }

    public function complete(PrivacyRequest $privacyRequest, User $actor): PrivacyRequest
    {
        $privacyRequest->forceFill(['completed_at' => now()])->save();

        return $this->transition(
            $privacyRequest,
            PrivacyRequestStatus::Completed,
            PrivacyRequestEventType::Completed,
            $actor,
            userVisibleMessage: 'اكتملت معالجة طلب حذف حسابك.',
        );
    }

    public function fail(PrivacyRequest $privacyRequest, User $actor, string $summary): PrivacyRequest
    {
        $privacyRequest->forceFill([
            'decision_summary' => $summary,
        ])->save();

        return $this->transition(
            $privacyRequest,
            PrivacyRequestStatus::Failed,
            PrivacyRequestEventType::Failed,
            $actor,
        );
    }

    public function assertExecutionReady(PrivacyRequest $privacyRequest, User $actor, Request $request): void
    {
        if ($privacyRequest->request_type !== PrivacyRequestType::AccountDeletion) {
            throw new InvalidArgumentException('Unsupported privacy request type.');
        }

        if ($privacyRequest->status !== PrivacyRequestStatus::Approved) {
            throw new InvalidArgumentException('Privacy request is not approved.');
        }

        if ($privacyRequest->identity_verified_at === null) {
            throw new InvalidArgumentException('Identity verification is missing.');
        }

        if (! $actor->can('privacy_requests.execute')) {
            throw new AuthorizationException('You are not allowed to execute account deletion.');
        }

        if (! SensitiveAccessVerification::isRecentlyVerified($request)) {
            throw new AuthorizationException('Executor re-verification is required.');
        }

        $ttlSeconds = max(60, (int) config('privacy.account_deletion.identity_verification_ttl_seconds', 900));

        if ($privacyRequest->identity_verified_at->lt(now()->subSeconds($ttlSeconds))) {
            throw new AuthorizationException('Identity verification for this request has expired.');
        }
    }

    public function hasActiveRequest(User $user, PrivacyRequestType $type): bool
    {
        return PrivacyRequest::query()
            ->where('user_id', $user->id)
            ->where('request_type', $type)
            ->whereIn('status', [
                PrivacyRequestStatus::Submitted,
                PrivacyRequestStatus::IdentityVerificationRequired,
                PrivacyRequestStatus::UnderReview,
                PrivacyRequestStatus::Approved,
                PrivacyRequestStatus::Processing,
            ])
            ->exists();
    }

    private function transition(
        PrivacyRequest $privacyRequest,
        PrivacyRequestStatus $toStatus,
        PrivacyRequestEventType $eventType,
        User $actor,
        ?string $identityVerificationMethod = null,
        ?string $userVisibleMessage = null,
        ?Request $request = null,
    ): PrivacyRequest {
        $fromStatus = $privacyRequest->status;

        if ($fromStatus->isTerminal()) {
            throw new InvalidArgumentException('Cannot transition a terminal privacy request.');
        }

        $privacyRequest->forceFill([
            'status' => $toStatus,
            'identity_verification_method' => $identityVerificationMethod ?? $privacyRequest->identity_verification_method,
            'identity_verified_at' => $identityVerificationMethod !== null ? now() : $privacyRequest->identity_verified_at,
        ])->save();

        $this->recordEvent(
            $privacyRequest,
            $eventType,
            $actor,
            $toStatus,
            fromStatus: $fromStatus,
            userVisibleMessage: $userVisibleMessage,
        );

        return $privacyRequest->fresh();
    }

    private function recordEvent(
        PrivacyRequest $privacyRequest,
        PrivacyRequestEventType $eventType,
        User $actor,
        PrivacyRequestStatus $toStatus,
        ?PrivacyRequestStatus $fromStatus = null,
        ?string $internalComment = null,
        ?string $userVisibleMessage = null,
    ): void {
        PrivacyRequestEvent::query()->create([
            'privacy_request_id' => $privacyRequest->id,
            'actor_id' => $actor->id,
            'actor_type' => 'user',
            'event' => $eventType->value,
            'from_status' => $fromStatus?->value,
            'to_status' => $toStatus->value,
            'internal_comment' => $internalComment,
            'user_visible_message' => $userVisibleMessage,
            'occurred_at' => now(),
        ]);
    }

    private function assertOwner(PrivacyRequest $privacyRequest, User $user): void
    {
        if ($privacyRequest->user_id !== $user->id) {
            throw new AuthorizationException('You cannot access this privacy request.');
        }
    }

    private function assertStaff(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new AuthorizationException('You are not allowed to perform this action.');
        }
    }
}
