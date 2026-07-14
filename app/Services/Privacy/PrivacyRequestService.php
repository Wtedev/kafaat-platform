<?php

namespace App\Services\Privacy;

use App\Enums\AccountStatus;
use App\Enums\AuditLogResult;
use App\Enums\PrivacyRequestEventType;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacyRequestType;
use App\Models\PrivacyRequest;
use App\Models\PrivacyRequestEvent;
use App\Models\PrivacyCorrectionPayload;
use App\Models\User;
use App\Data\Privacy\PrivacyAccessResponseSnapshot;
use App\Enums\PrivacyCorrectionFieldCode;
use App\Enums\UserActivityAction;
use App\Services\Identity\PersonNameService;
use App\Services\Identity\IdentityNumberService;
use App\Services\Access\SensitiveAccessVerification;
use App\Services\Audit\AuditLogger;
use App\Services\UserActivityLogger;
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
        private readonly PrivacyCorrectionService $correctionService,
        private readonly PrivacyAccessResponseBuilder $accessResponseBuilder,
        private readonly PrivacyRequestNotificationService $notifications,
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
                'privacy_request.created',
                AuditLogResult::Success,
                $user,
                metadata: ['privacy_request_uuid' => $privacyRequest->uuid, 'request_type' => $privacyRequest->request_type->value],
                request: $request,
            );

            UserActivityLogger::log($user, UserActivityAction::PrivacyRequestSubmitted, 'طلب حذف الحساب.');

            return $privacyRequest;
        });
    }

    public function submitDataAccess(User $user, Request $request): PrivacyRequest
    {
        $this->assertCanSubmitPrivacyRequest($user);

        if ($this->hasActiveRequest($user, PrivacyRequestType::DataAccess)) {
            throw new InvalidArgumentException('An active data access request already exists.');
        }

        return DB::transaction(function () use ($user, $request): PrivacyRequest {
            $privacyRequest = $this->createRequest(
                $user,
                PrivacyRequestType::DataAccess,
                PrivacyRequestStatus::Submitted,
                null,
            );

            $this->recordEvent(
                $privacyRequest,
                PrivacyRequestEventType::Submitted,
                $user,
                PrivacyRequestStatus::Submitted,
                userVisibleMessage: 'استلمنا طلب الوصول إلى بياناتك. سيراجعه فريق الخصوصية.',
            );

            $this->logPrivacyRequestCreated($user, $privacyRequest, $request, UserActivityAction::PrivacyAccessRequested);

            return $privacyRequest;
        });
    }

    public function submitDataExport(User $user, Request $request, string $password): PrivacyRequest
    {
        $this->assertCanSubmitPrivacyRequest($user);

        if ($this->hasActiveRequest($user, PrivacyRequestType::DataExport)) {
            throw new InvalidArgumentException('An active data export request already exists.');
        }

        if ($this->hasDownloadableExportFile($user)) {
            throw new InvalidArgumentException('A downloadable export file is still available.');
        }

        if (! Hash::check($password, (string) $user->password)) {
            app(\App\Services\Security\SecurityLogService::class)->record(
                'privacy_export.verification_failed',
                \App\Enums\SecurityLogResult::Failed,
                \App\Enums\SecurityLogSeverity::Warning,
                $user,
                request: $request,
            );

            throw ValidationException::withMessages(['password' => 'كلمة المرور غير صحيحة.']);
        }

        SensitiveAccessVerification::markVerified($request);

        return DB::transaction(function () use ($user, $request): PrivacyRequest {
            $privacyRequest = $this->createRequest(
                $user,
                PrivacyRequestType::DataExport,
                PrivacyRequestStatus::Submitted,
                null,
            );

            $this->recordEvent(
                $privacyRequest,
                PrivacyRequestEventType::Submitted,
                $user,
                PrivacyRequestStatus::Submitted,
                userVisibleMessage: 'استلمنا طلب تصدير بياناتك. سيراجعه فريق الخصوصية قبل توليد الملف.',
            );

            $this->auditLogger->recordOrFail(
                $user,
                'privacy_export.request_created',
                AuditLogResult::Success,
                $user,
                metadata: [
                    'privacy_request_uuid' => $privacyRequest->uuid,
                    'request_type' => $privacyRequest->request_type->value,
                ],
                request: $request,
            );

            UserActivityLogger::log($user, UserActivityAction::PrivacyExportRequested);
            $this->notifications->notifyRequestCreated($user, $privacyRequest);

            return $privacyRequest;
        });
    }

    public function markExportProcessing(PrivacyRequest $privacyRequest, User $actor): PrivacyRequest
    {
        if ($privacyRequest->request_type !== PrivacyRequestType::DataExport) {
            throw new InvalidArgumentException('Not a data export request.');
        }

        $this->assertStaff($actor, 'privacy_requests.export.generate');

        return $this->transition(
            $privacyRequest,
            PrivacyRequestStatus::Processing,
            PrivacyRequestEventType::ProcessingStarted,
            $actor,
            userVisibleMessage: 'جاري تجهيز ملف تصدير بياناتك.',
        );
    }

    public function completeExportRequest(PrivacyRequest $privacyRequest, User $actor): PrivacyRequest
    {
        if ($privacyRequest->request_type !== PrivacyRequestType::DataExport) {
            throw new InvalidArgumentException('Not a data export request.');
        }

        $message = 'ملف تصدير بياناتك جاهز للتنزيل من مركز الخصوصية. يرجى تنزيله قبل انتهاء الصلاحية.';

        $privacyRequest->forceFill([
            'completed_at' => now(),
            'user_visible_response' => $message,
        ])->save();

        return $this->transition(
            $privacyRequest,
            PrivacyRequestStatus::Completed,
            PrivacyRequestEventType::Completed,
            $actor,
            userVisibleMessage: $message,
        );
    }

    public function hasDownloadableExportFile(User $user): bool
    {
        return \App\Models\PrivacyExportFile::query()
            ->where('user_id', $user->id)
            ->where('status', \App\Enums\PrivacyExportFileStatus::Ready->value)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $valuePayload
     */
    public function submitDataCorrection(
        User $user,
        PrivacyCorrectionFieldCode $field,
        string $reason,
        array $valuePayload,
        Request $httpRequest,
        ?string $password = null,
    ): PrivacyRequest {
        $this->assertCanSubmitPrivacyRequest($user);

        if ($field->isSelfServiceFor($user)) {
            throw ValidationException::withMessages([
                'field_code' => 'يمكنك تعديل هذا الحقل مباشرة من ملفك الشخصي.',
            ]);
        }

        if ($this->hasActiveRequest($user, PrivacyRequestType::DataCorrection)) {
            throw new InvalidArgumentException('An active correction request already exists.');
        }

        if ($field->requiresSensitiveVerification()) {
            if ($password === null || ! Hash::check($password, (string) $user->password)) {
                app(\App\Services\Security\SecurityLogService::class)->record(
                    'privacy_correction.verification_failed',
                    \App\Enums\SecurityLogResult::Failed,
                    \App\Enums\SecurityLogSeverity::Warning,
                    $user,
                    request: $httpRequest,
                );

                throw ValidationException::withMessages(['password' => 'كلمة المرور غير صحيحة.']);
            }

            SensitiveAccessVerification::markVerified($httpRequest);
        }

        return DB::transaction(function () use ($user, $field, $reason, $valuePayload, $httpRequest): PrivacyRequest {
            $details = [
                'reason' => Str::limit($reason, 1000),
                'field_code' => $field->value,
            ];

            if ($field === PrivacyCorrectionFieldCode::StructuredName) {
                $details = array_merge($details, PersonNameService::normalizedParts($valuePayload));
            } elseif ($field === PrivacyCorrectionFieldCode::BirthDate) {
                $details['new_value'] = (string) ($valuePayload['birth_date'] ?? '');
            } elseif ($field === PrivacyCorrectionFieldCode::IdentityNumber) {
                $identityType = \App\Enums\IdentityType::from((string) ($valuePayload['identity_type'] ?? ''));
                $details['identity_type'] = $identityType->value;
            }

            $privacyRequest = $this->createRequest(
                $user,
                PrivacyRequestType::DataCorrection,
                PrivacyRequestStatus::Submitted,
                $details,
                correctionFieldCode: $field->value,
            );

            if ($field === PrivacyCorrectionFieldCode::IdentityNumber) {
                $identityType = \App\Enums\IdentityType::from((string) ($valuePayload['identity_type'] ?? ''));
                try {
                    $this->correctionService->storeSensitivePayload(
                        $privacyRequest,
                        $field,
                        (string) ($valuePayload['identity_number'] ?? ''),
                        $identityType,
                    );
                } catch (InvalidArgumentException $exception) {
                    if ($exception->getMessage() === 'duplicate_identity') {
                        throw ValidationException::withMessages([
                            'identity_number' => IdentityNumberService::DUPLICATE_MESSAGE,
                        ]);
                    }

                    throw $exception;
                }
            } elseif ($field === PrivacyCorrectionFieldCode::Email) {
                $this->correctionService->storeSensitivePayload(
                    $privacyRequest,
                    $field,
                    (string) ($valuePayload['email'] ?? ''),
                );
            }

            $this->recordEvent(
                $privacyRequest,
                PrivacyRequestEventType::Submitted,
                $user,
                PrivacyRequestStatus::Submitted,
                userVisibleMessage: 'استلمنا طلب تصحيح بياناتك. سيراجعه فريق الخصوصية.',
            );

            $this->logPrivacyRequestCreated($user, $privacyRequest, $httpRequest, UserActivityAction::PrivacyCorrectionRequested);

            return $privacyRequest->fresh(['correctionPayload']);
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

        if ($privacyRequest->request_type === PrivacyRequestType::DataExport) {
            $this->auditLogger->recordOrFail(
                $actor,
                'privacy_export.approved',
                AuditLogResult::Success,
                $privacyRequest->user,
                metadata: ['privacy_request_uuid' => $privacyRequest->uuid],
            );
        }

        return $this->transition(
            $privacyRequest,
            PrivacyRequestStatus::Approved,
            PrivacyRequestEventType::Approved,
            $actor,
            userVisibleMessage: match ($privacyRequest->request_type) {
                PrivacyRequestType::AccountDeletion => 'تمت الموافقة على طلبك. سيبدأ التنفيذ بعد اعتماد خطة الحذف.',
                PrivacyRequestType::DataExport => 'تمت الموافقة على طلب تصدير بياناتك. سيبدأ تجهيز الملف بعد مراجعة فريق الخصوصية.',
                default => 'تمت الموافقة على طلبك.',
            },
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
            userVisibleMessage: $this->rejectionMessageForType($privacyRequest->request_type),
        );

        $this->restoreUserAfterTerminalRequest($privacyRequest);

        UserActivityLogger::log($privacyRequest->user, UserActivityAction::PrivacyRequestRejected);
        $this->notifications->notifyStatusChange($privacyRequest->user, $privacyRequest);

        return $result;
    }

    public function partiallyApprove(PrivacyRequest $privacyRequest, User $actor, ?string $summary = null): PrivacyRequest
    {
        $this->assertStaff($actor, 'privacy_requests.approve');

        $privacyRequest->forceFill(['decision_summary' => $summary])->save();

        return $this->transition(
            $privacyRequest,
            PrivacyRequestStatus::PartiallyApproved,
            PrivacyRequestEventType::PartiallyApproved,
            $actor,
            userVisibleMessage: 'تمت الموافقة جزئياً على طلبك.',
        );
    }

    public function completeAccessRequest(PrivacyRequest $privacyRequest, User $actor, ?string $userVisibleResponse = null): PrivacyRequest
    {
        $this->assertStaff($actor, 'privacy_requests.review');

        if ($privacyRequest->request_type !== PrivacyRequestType::DataAccess) {
            throw new InvalidArgumentException('Not a data access request.');
        }

        $snapshot = $this->accessResponseBuilder->buildFor($privacyRequest->user);
        $responseText = $userVisibleResponse ?? $this->formatAccessResponseForUser($snapshot);

        $privacyRequest->forceFill([
            'access_response' => $snapshot->toArray(),
            'user_visible_response' => $responseText,
            'completed_at' => now(),
        ])->save();

        $result = $this->transition(
            $privacyRequest,
            PrivacyRequestStatus::Completed,
            PrivacyRequestEventType::AccessResponseCreated,
            $actor,
            userVisibleMessage: $responseText,
        );

        $this->auditLogger->recordOrFail(
            $actor,
            'privacy_access.response_created',
            AuditLogResult::Success,
            $privacyRequest->user,
            metadata: ['privacy_request_uuid' => $privacyRequest->uuid],
        );

        UserActivityLogger::log($privacyRequest->user, UserActivityAction::PrivacyAccessCompleted);
        $this->notifications->notifyStatusChange($privacyRequest->user, $privacyRequest);

        return $result;
    }

    public function applyCorrection(PrivacyRequest $privacyRequest, User $actor): PrivacyRequest
    {
        $this->correctionService->apply($privacyRequest, $actor);

        $certificatesNote = $this->correctionService->userHasCertificates($privacyRequest->user)
            ? ' تم تصحيح بيانات الحساب. الشهادات السابقة لم تُعدَّل تلقائياً.'
            : ' تم تصحيح بيانات الحساب.';

        $privacyRequest->forceFill([
            'completed_at' => now(),
            'user_visible_response' => 'اكتمل تنفيذ طلب التصحيح.'.$certificatesNote,
        ])->save();

        $result = $this->transition(
            $privacyRequest,
            PrivacyRequestStatus::Completed,
            PrivacyRequestEventType::CorrectionApplied,
            $actor,
            userVisibleMessage: $privacyRequest->user_visible_response,
        );

        UserActivityLogger::log($privacyRequest->user, UserActivityAction::PrivacyCorrectionCompleted);
        $this->notifications->notifyStatusChange($privacyRequest->user, $privacyRequest);

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
            userVisibleMessage: $this->cancellationMessageForType($privacyRequest->request_type),
        );

        $this->restoreUserAfterTerminalRequest($privacyRequest);

        UserActivityLogger::log($privacyRequest->user, UserActivityAction::PrivacyRequestCancelled);

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
                PrivacyRequestStatus::PartiallyApproved,
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

    private function assertCanSubmitPrivacyRequest(User $user): void
    {
        if ($user->isAnonymized() || $user->account_status === AccountStatus::DeletionProcessing) {
            throw new InvalidArgumentException('This account cannot submit privacy requests.');
        }
    }

    private function createRequest(
        User $user,
        PrivacyRequestType $type,
        PrivacyRequestStatus $status,
        ?array $details,
        ?string $correctionFieldCode = null,
    ): PrivacyRequest {
        return PrivacyRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'request_type' => $type,
            'status' => $status,
            'request_details' => $details,
            'correction_field_code' => $correctionFieldCode,
            'due_at' => now()->addDays(30),
        ]);
    }

    private function logPrivacyRequestCreated(
        User $user,
        PrivacyRequest $privacyRequest,
        Request $request,
        UserActivityAction $activity,
    ): void {
        $this->auditLogger->recordOrFail(
            $user,
            'privacy_request.created',
            AuditLogResult::Success,
            $user,
            metadata: [
                'privacy_request_uuid' => $privacyRequest->uuid,
                'request_type' => $privacyRequest->request_type->value,
            ],
            request: $request,
        );

        UserActivityLogger::log($user, $activity);
        $this->notifications->notifyRequestCreated($user, $privacyRequest);
    }

    private function restoreUserAfterTerminalRequest(PrivacyRequest $privacyRequest): void
    {
        if ($privacyRequest->request_type !== PrivacyRequestType::AccountDeletion) {
            return;
        }

        $privacyRequest->user->forceFill([
            'account_status' => AccountStatus::Active,
            'is_active' => true,
            'deletion_request_id' => null,
        ])->save();
    }

    private function rejectionMessageForType(PrivacyRequestType $type): string
    {
        return match ($type) {
            PrivacyRequestType::AccountDeletion => 'تم رفض طلب حذف حسابك.',
            PrivacyRequestType::DataAccess => 'تم رفض طلب الوصول إلى بياناتك.',
            PrivacyRequestType::DataCorrection => 'تم رفض طلب تصحيح بياناتك.',
            PrivacyRequestType::DataExport => 'تم رفض طلب تصدير بياناتك.',
        };
    }

    private function cancellationMessageForType(PrivacyRequestType $type): string
    {
        return match ($type) {
            PrivacyRequestType::AccountDeletion => 'تم إلغاء طلب حذف حسابك.',
            PrivacyRequestType::DataAccess => 'تم إلغاء طلب الوصول إلى بياناتك.',
            PrivacyRequestType::DataCorrection => 'تم إلغاء طلب تصحيح بياناتك.',
            PrivacyRequestType::DataExport => 'تم إلغاء طلب تصدير بياناتك.',
        };
    }

    private function formatAccessResponseForUser(PrivacyAccessResponseSnapshot $snapshot): string
    {
        $lines = collect($snapshot->categories)
            ->map(fn (array $category): string => '• '.$category['category'].': '.$category['summary'])
            ->implode("\n");

        return "فيما يلي فئات البيانات التي تحتفظ بها المنصة عنك:\n".$lines;
    }
}
