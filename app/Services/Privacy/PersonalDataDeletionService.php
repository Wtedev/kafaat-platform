<?php

namespace App\Services\Privacy;

use App\Data\Privacy\DeletionExecutionContext;
use App\Data\Privacy\DeletionPlanSnapshot;
use App\Enums\AccountStatus;
use App\Enums\AuditLogResult;
use App\Enums\DataDeletionPlanStatus;
use App\Enums\DataDeletionPlanStepStatus;
use App\Enums\DeletionHandlerName;
use App\Enums\PrivacyRequestEventType;
use App\Models\DataDeletionPlan;
use App\Models\DataDeletionPlanStep;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Privacy\Deletion\DeletionHandlerRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class PersonalDataDeletionService
{
    private const LOCK_PREFIX = 'privacy:deletion:execute:';

    public function __construct(
        private readonly PrivacyRequestService $privacyRequestService,
        private readonly DataDeletionPlanService $deletionPlanService,
        private readonly DeletionHandlerRegistry $handlerRegistry,
        private readonly AuditLogger $auditLogger,
        private readonly AccountDeactivationService $accountDeactivationService,
    ) {}

    public function executeApprovedPlan(
        PrivacyRequest $privacyRequest,
        DataDeletionPlan $plan,
        User $actor,
        Request $request,
    ): void {
        $this->privacyRequestService->assertExecutionReady($privacyRequest, $actor, $request);

        if ($plan->privacy_request_id !== $privacyRequest->id) {
            throw new InvalidArgumentException('Deletion plan does not belong to this privacy request.');
        }

        if ($plan->status !== DataDeletionPlanStatus::Approved) {
            throw new InvalidArgumentException('Deletion plan is not approved.');
        }

        if ($privacyRequest->user->isProtectedAdminUser()) {
            throw new AuthorizationException('Protected admin accounts cannot be anonymized.');
        }

        $lock = Cache::lock(self::LOCK_PREFIX.$plan->uuid, 600);

        if (! $lock->get()) {
            throw new InvalidArgumentException('Deletion plan execution is already in progress.');
        }

        try {
            $this->runExecution($privacyRequest, $plan, $actor, $request);
        } finally {
            $lock->release();
        }
    }

    private function runExecution(
        PrivacyRequest $privacyRequest,
        DataDeletionPlan $plan,
        User $actor,
        Request $request,
    ): void {
        $target = User::query()->lockForUpdate()->findOrFail($privacyRequest->user_id);

        if ($target->account_status === AccountStatus::Anonymized) {
            return;
        }

        $this->auditLogger->recordOrFail(
            $actor,
            'deletion.execution_started',
            AuditLogResult::Success,
            $target,
            metadata: [
                'privacy_request_uuid' => $privacyRequest->uuid,
                'deletion_plan_uuid' => $plan->uuid,
            ],
            request: $request,
        );

        $plan->forceFill([
            'status' => DataDeletionPlanStatus::Executing,
            'execution_started_at' => $plan->execution_started_at ?? now(),
        ])->save();

        $this->privacyRequestService->markProcessing($privacyRequest, $actor);

        $target->forceFill([
            'is_active' => false,
            'account_status' => AccountStatus::DeletionProcessing,
        ])->save();

        $this->accountDeactivationService->invalidateSessions($target);

        $snapshot = $this->deletionPlanService->snapshot($plan);
        $failedHandlers = [];

        foreach (DeletionHandlerName::executionOrder() as $handlerName) {
            $step = DataDeletionPlanStep::query()
                ->where('data_deletion_plan_id', $plan->id)
                ->where('handler', $handlerName)
                ->first();

            if ($step === null) {
                continue;
            }

            if ($step->status === DataDeletionPlanStepStatus::Completed) {
                continue;
            }

            try {
                $this->executeStep($privacyRequest, $plan, $target, $actor, $snapshot, $step, $request);
            } catch (Throwable $exception) {
                $failedHandlers[] = $handlerName->value;

                $step->forceFill([
                    'status' => DataDeletionPlanStepStatus::Failed,
                    'failure_code' => class_basename($exception),
                    'completed_at' => now(),
                ])->save();

                $plan->forceFill([
                    'status' => DataDeletionPlanStatus::Failed,
                    'failure_summary' => 'Handler failed: '.$handlerName->value,
                ])->save();

                $this->privacyRequestService->fail(
                    $privacyRequest,
                    $actor,
                    'Partial deletion failure at handler '.$handlerName->value,
                );

                $this->auditLogger->recordOrFail(
                    $actor,
                    'deletion.execution_failed',
                    AuditLogResult::Failure,
                    metadata: [
                        'privacy_request_uuid' => $privacyRequest->uuid,
                        'deletion_plan_uuid' => $plan->uuid,
                        'handler' => $handlerName->value,
                    ],
                    request: $request,
                );

                throw $exception;
            }
        }

        if ($failedHandlers !== []) {
            return;
        }

        $plan->forceFill([
            'status' => DataDeletionPlanStatus::Completed,
            'execution_completed_at' => now(),
            'failure_summary' => null,
        ])->save();

        $this->privacyRequestService->complete($privacyRequest, $actor);

        $this->auditLogger->recordOrFail(
            $actor,
            'deletion.execution_completed',
            AuditLogResult::Success,
            metadata: [
                'privacy_request_uuid' => $privacyRequest->uuid,
                'deletion_plan_uuid' => $plan->uuid,
                'anonymized_user_id' => $target->id,
            ],
            request: $request,
        );
    }

    private function executeStep(
        PrivacyRequest $privacyRequest,
        DataDeletionPlan $plan,
        User $target,
        User $actor,
        DeletionPlanSnapshot $snapshot,
        DataDeletionPlanStep $step,
        Request $request,
    ): void {
        $step->forceFill([
            'status' => DataDeletionPlanStepStatus::Running,
            'started_at' => $step->started_at ?? now(),
            'attempts' => $step->attempts + 1,
        ])->save();

        $context = new DeletionExecutionContext(
            privacyRequest: $privacyRequest,
            plan: $plan,
            target: $target->fresh(),
            actor: $actor,
            snapshot: $snapshot,
            request: $request,
            step: $step,
        );

        $this->handlerRegistry->get($step->handler)->handle($context);

        $step->forceFill([
            'status' => DataDeletionPlanStepStatus::Completed,
            'completed_at' => now(),
            'failure_code' => null,
        ])->save();
    }
}
