<?php

namespace App\Services\ProgramRegistration;

use App\Enums\AuditLogResult;
use App\Enums\InboxNotificationType;
use App\Enums\NotificationTargetType;
use App\Enums\RegistrationStatus;
use App\Exceptions\ProgramCapacityExceededException;
use App\Exceptions\RegistrationNotEligibleException;
use App\Models\InboxNotification;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\ProgramRegistrationService;
use Illuminate\Support\Collection;
use Throwable;

final class BulkProgramRegistrationProcessor
{
    public const QUEUE_THRESHOLD = 50;

    public function __construct(
        private readonly ProgramRegistrationService $registrations,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  Collection<int, ProgramRegistration>  $records
     * @param  array<string, mixed>  $filters
     * @return array{approved: int, skipped: int, capacity_blocked: int, total: int}
     */
    public function approve(
        Collection $records,
        User $actor,
        TrainingProgram $program,
        array $filters = [],
    ): array {
        $approved = 0;
        $skipped = 0;
        $capacityBlocked = 0;

        foreach ($records as $record) {
            if (! $record instanceof ProgramRegistration) {
                continue;
            }

            if ((int) $record->training_program_id !== (int) $program->id) {
                $skipped++;

                continue;
            }

            if ($record->status === RegistrationStatus::Approved) {
                $skipped++;

                continue;
            }

            if ($record->status !== RegistrationStatus::Pending) {
                $skipped++;

                continue;
            }

            try {
                $before = $record->status;
                $result = $this->registrations->approve($record, $actor);
                if ($result->status === RegistrationStatus::Approved && $before !== RegistrationStatus::Approved) {
                    $approved++;
                } else {
                    $skipped++;
                }
            } catch (ProgramCapacityExceededException) {
                $capacityBlocked++;
            } catch (RegistrationNotEligibleException) {
                $skipped++;
            }
        }

        $summary = [
            'approved' => $approved,
            'skipped' => $skipped,
            'capacity_blocked' => $capacityBlocked,
            'total' => $records->count(),
        ];

        $this->audit($actor, $program, 'program_registrations.bulk_approve', $summary, $filters);

        return $summary;
    }

    /**
     * @param  Collection<int, ProgramRegistration>  $records
     * @param  array<string, mixed>  $filters
     * @return array{rejected: int, skipped: int, total: int}
     */
    public function reject(
        Collection $records,
        User $actor,
        TrainingProgram $program,
        ?string $reason = null,
        array $filters = [],
    ): array {
        $rejected = 0;
        $skipped = 0;

        foreach ($records as $record) {
            if (! $record instanceof ProgramRegistration) {
                continue;
            }

            if ((int) $record->training_program_id !== (int) $program->id) {
                $skipped++;

                continue;
            }

            if ($record->status === RegistrationStatus::Rejected) {
                $skipped++;

                continue;
            }

            if ($record->status !== RegistrationStatus::Pending) {
                $skipped++;

                continue;
            }

            $this->registrations->reject($record, $reason);
            $rejected++;
        }

        $summary = [
            'rejected' => $rejected,
            'skipped' => $skipped,
            'total' => $records->count(),
        ];

        $this->audit($actor, $program, 'program_registrations.bulk_reject', $summary + ['reason' => $reason], $filters);

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{exported: int, total: int}
     */
    public function auditExport(
        User $actor,
        TrainingProgram $program,
        int $exportedCount,
        array $filters = [],
    ): array {
        $summary = [
            'exported' => $exportedCount,
            'total' => $exportedCount,
        ];

        $this->audit($actor, $program, 'program_registrations.bulk_export', $summary, $filters);

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function notifyActor(User $actor, string $title, string $message, array $summary = []): void
    {
        InboxNotification::query()->create([
            'user_id' => $actor->id,
            'title' => $title,
            'message' => $message,
            'type' => InboxNotificationType::GeneralMessage,
            'sender_id' => null,
            'target_type' => NotificationTargetType::SingleUser,
            'context' => $summary,
        ]);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $filters
     */
    private function audit(
        User $actor,
        TrainingProgram $program,
        string $action,
        array $summary,
        array $filters,
    ): void {
        try {
            $this->auditLogger->record(
                $actor,
                $action,
                AuditLogResult::Success,
                resource: $program,
                metadata: [
                    'program_id' => $program->id,
                    'summary' => $summary,
                    'filters' => $filters,
                ],
            );
        } catch (Throwable) {
            // Bulk work must not fail solely because audit persistence failed.
        }
    }
}
