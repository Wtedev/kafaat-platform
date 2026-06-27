<?php

namespace App\Services\Privacy;

use App\Data\Privacy\DeletionPlanSnapshot;
use App\Enums\AuditLogResult;
use App\Enums\DataDeletionPlanStatus;
use App\Enums\DataDeletionPlanStepStatus;
use App\Enums\DeletionHandlerName;
use App\Models\DataDeletionPlan;
use App\Models\DataDeletionPlanStep;
use App\Models\PrivacyRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class DataDeletionPlanService
{
    public function __construct(
        private readonly DataDeletionPlanBuilder $planBuilder,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function createDraft(PrivacyRequest $request, User $actor): DataDeletionPlan
    {
        if (! $actor->can('privacy_requests.review')) {
            throw new AuthorizationException('You are not allowed to create deletion plans.');
        }

        if ($request->deletionPlan !== null) {
            throw new InvalidArgumentException('A deletion plan already exists for this request.');
        }

        $snapshot = $this->planBuilder->buildForUser($request->user);

        return DB::transaction(function () use ($request, $actor, $snapshot): DataDeletionPlan {
            $plan = DataDeletionPlan::query()->create([
                'uuid' => (string) Str::uuid(),
                'privacy_request_id' => $request->id,
                'user_id' => $request->user_id,
                'status' => DataDeletionPlanStatus::ReadyForReview,
                'plan_snapshot' => $snapshot->toArray(),
            ]);

            foreach (DeletionHandlerName::executionOrder() as $handler) {
                DataDeletionPlanStep::query()->create([
                    'data_deletion_plan_id' => $plan->id,
                    'handler' => $handler,
                    'status' => DataDeletionPlanStepStatus::Pending,
                ]);
            }

            $this->auditLogger->recordOrFail(
                $actor,
                'deletion_plan.created',
                AuditLogResult::Success,
                $request->user,
                metadata: [
                    'privacy_request_uuid' => $request->uuid,
                    'deletion_plan_uuid' => $plan->uuid,
                ],
            );

            return $plan->fresh(['steps']);
        });
    }

    public function approve(DataDeletionPlan $plan, User $actor): DataDeletionPlan
    {
        if (! $actor->can('privacy_requests.approve')) {
            throw new AuthorizationException('You are not allowed to approve deletion plans.');
        }

        if ($plan->status !== DataDeletionPlanStatus::ReadyForReview) {
            throw new InvalidArgumentException('Deletion plan is not ready for approval.');
        }

        $plan->forceFill([
            'status' => DataDeletionPlanStatus::Approved,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ])->save();

        $this->auditLogger->recordOrFail(
            $actor,
            'deletion_plan.approved',
            AuditLogResult::Success,
            $plan->user,
            metadata: [
                'deletion_plan_uuid' => $plan->uuid,
            ],
        );

        return $plan->fresh();
    }

    public function snapshot(DataDeletionPlan $plan): DeletionPlanSnapshot
    {
        return DeletionPlanSnapshot::fromArray($plan->plan_snapshot ?? []);
    }
}
