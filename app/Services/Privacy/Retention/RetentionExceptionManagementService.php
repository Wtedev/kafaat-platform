<?php

namespace App\Services\Privacy\Retention;

use App\Enums\AuditLogResult;
use App\Enums\RetentionExceptionScope;
use App\Enums\RetentionExceptionStatus;
use App\Models\RetentionException;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class RetentionExceptionManagementService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): RetentionException
    {
        if (! $actor->can('retention_exceptions.manage')) {
            throw ValidationException::withMessages(['permission' => 'Denied.']);
        }

        $validated = Validator::make($data, [
            'resource_type' => ['required', 'string'],
            'scope' => ['required', 'string'],
            'resource_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason_code' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:10'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'review_at' => ['required', 'date'],
        ])->validate();

        $scope = RetentionExceptionScope::from($validated['scope']);

        if ($scope === RetentionExceptionScope::SingleResource && empty($validated['resource_id'])) {
            throw ValidationException::withMessages(['resource_id' => 'Required for single resource scope.']);
        }

        if ($scope === RetentionExceptionScope::UserAllResources && empty($validated['user_id'])) {
            throw ValidationException::withMessages(['user_id' => 'Required for user-wide scope.']);
        }

        if ($scope === RetentionExceptionScope::ResourceTypeAll && ! $actor->can('retention_policies.activate')) {
            throw ValidationException::withMessages(['scope' => 'Resource-type-wide exceptions require elevated approval.']);
        }

        $exception = RetentionException::query()->create([
            ...$validated,
            'scope' => $scope,
            'reason_code' => \App\Enums\RetentionExceptionReasonCode::from($validated['reason_code']),
            'status' => RetentionExceptionStatus::Active,
            'approved_by' => $actor->id,
        ]);

        $this->auditLogger->recordOrFail(
            $actor,
            'retention_exception.created',
            AuditLogResult::Success,
            metadata: [
                'exception_uuid' => $exception->uuid,
                'resource_type' => $exception->resource_type,
                'scope' => $exception->scope->value,
            ],
        );

        $this->auditLogger->recordOrFail(
            $actor,
            'retention_exception.approved',
            AuditLogResult::Success,
            metadata: ['exception_uuid' => $exception->uuid],
        );

        return $exception;
    }

    public function revoke(RetentionException $exception, User $actor): RetentionException
    {
        $exception->forceFill([
            'status' => RetentionExceptionStatus::Revoked,
            'revoked_by' => $actor->id,
            'revoked_at' => now(),
        ])->save();

        $this->auditLogger->recordOrFail(
            $actor,
            'retention_exception.revoked',
            AuditLogResult::Success,
            metadata: ['exception_uuid' => $exception->uuid],
        );

        return $exception->fresh();
    }
}
