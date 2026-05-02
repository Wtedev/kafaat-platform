<?php

namespace App\Services;

use App\Enums\ProgramStatus;
use App\Enums\RegistrationStatus;
use App\Exceptions\ProgramBelongsToLearningPathException;
use App\Exceptions\ProgramCapacityExceededException;
use App\Exceptions\RegistrationNotApprovedException;
use App\Exceptions\RegistrationWindowClosedException;
use App\Models\LearningPath;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Notifications\ProgramRegistrationApproved;
use App\Notifications\ProgramRegistrationRejected;
use App\Services\Inbox\InboxNotificationService;

class ProgramRegistrationService
{
    public function __construct(
        private readonly EmailLogService $emailLogService,
        private readonly CertificateService $certificateService,
        private readonly InboxNotificationService $inboxNotifications,
        private readonly ProgressService $progressService,
    ) {}

    /**
     * Register a user to a training program.
     *
     * @throws ProgramBelongsToLearningPathException
     * @throws RegistrationWindowClosedException
     * @throws ProgramCapacityExceededException
     */
    public function register(TrainingProgram $program, User $user): ProgramRegistration
    {
        if ($program->learning_path_id !== null) {
            throw new ProgramBelongsToLearningPathException;
        }

        if (! $program->isRegistrationOpen()) {
            throw new RegistrationWindowClosedException;
        }

        // We do not check capacity at registration time — capacity is enforced on approval.
        // This allows registrations to be queued as pending even when the program is full,
        // to handle dropouts. Remove this comment if you want to block here too.

        return ProgramRegistration::firstOrCreate(
            [
                'training_program_id' => $program->id,
                'user_id' => $user->id,
            ],
            [
                'status' => RegistrationStatus::Pending,
            ]
        );
    }

    /**
     * Approve a pending registration.
     *
     * @throws ProgramCapacityExceededException
     */
    public function approve(ProgramRegistration $registration, User $approvedBy): ProgramRegistration
    {
        if ($registration->status === RegistrationStatus::Approved) {
            return $registration;
        }

        if (! $this->assertCapacityAndApproveRegistration($registration, $approvedBy)) {
            return $registration->fresh();
        }

        $registration = $registration->fresh();
        $this->sendProgramRegistrationApprovedNotifications($registration, $approvedBy);

        return $registration->fresh();
    }

    /**
     * Approve the user for every published program in the path (same DB transaction as path approval when used from PathRegistrationService).
     * Does not send email/inbox; caller should invoke {@see sendProgramRegistrationApprovedNotifications} after commit.
     *
     * @return list<ProgramRegistration> Registrations that newly became approved (for notifications).
     *
     * @throws ProgramCapacityExceededException
     */
    public function approveAllProgramsForPathMemberWithoutNotifications(
        LearningPath $path,
        User $user,
        User $approvedBy,
    ): array {
        $path->loadMissing('programs');

        $newlyApproved = [];

        foreach ($path->programs as $program) {
            if ($program->status !== ProgramStatus::Published) {
                continue;
            }

            if ((int) $program->learning_path_id !== (int) $path->id) {
                continue;
            }

            $reg = ProgramRegistration::query()->firstOrCreate(
                [
                    'training_program_id' => $program->id,
                    'user_id' => $user->id,
                ],
                [
                    'status' => RegistrationStatus::Pending,
                ],
            );

            if ($reg->status === RegistrationStatus::Approved) {
                continue;
            }

            if (in_array($reg->status, [RegistrationStatus::Rejected, RegistrationStatus::Cancelled], true)) {
                $reg->update([
                    'status' => RegistrationStatus::Pending,
                    'rejected_reason' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                ]);
                $reg->refresh();
            }

            $reg = $reg->fresh();

            if ($this->assertCapacityAndApproveRegistration($reg, $approvedBy)) {
                $newlyApproved[] = $reg->fresh();
            }
        }

        return $newlyApproved;
    }

    public function sendProgramRegistrationApprovedNotifications(ProgramRegistration $registration, User $approvedBy): void
    {
        $registration->loadMissing(['user', 'trainingProgram']);
        $program = $registration->trainingProgram;

        $this->emailLogService->send(
            recipient: $registration->user,
            notification: new ProgramRegistrationApproved($registration),
            templateKey: 'program_registration.approved',
            subject: 'Your Registration Has Been Approved — '.$program->title,
            sentBy: $approvedBy,
        );

        $this->inboxNotifications->registrationApprovedProgram($registration->user, $program, $approvedBy);
    }

    /**
     * @throws ProgramCapacityExceededException
     */
    private function assertCapacityAndApproveRegistration(ProgramRegistration $registration, User $approvedBy): bool
    {
        if ($registration->status === RegistrationStatus::Approved) {
            return false;
        }

        $program = $registration->trainingProgram;

        if ($program->capacity !== null) {
            $approvedCount = $program->registrations()
                ->where('status', RegistrationStatus::Approved->value)
                ->count();

            if ($approvedCount >= $program->capacity) {
                throw new ProgramCapacityExceededException;
            }
        }

        $registration->update([
            'status' => RegistrationStatus::Approved,
            'approved_by' => $approvedBy->id,
            'approved_at' => now(),
        ]);

        return true;
    }

    /**
     * Reject a registration with an optional reason.
     */
    public function reject(ProgramRegistration $registration, ?string $reason = null): ProgramRegistration
    {
        $registration->update([
            'status' => RegistrationStatus::Rejected,
            'rejected_reason' => $reason,
        ]);

        $registration->loadMissing(['user', 'trainingProgram']);

        $this->emailLogService->send(
            recipient: $registration->user,
            notification: new ProgramRegistrationRejected($registration),
            templateKey: 'program_registration.rejected',
            subject: 'Registration Update — '.$registration->trainingProgram->title,
        );

        $rejector = auth()->user();
        $this->inboxNotifications->registrationRejectedProgram(
            $registration->user,
            $registration->trainingProgram,
            $reason,
            $rejector instanceof User ? $rejector : null,
        );

        return $registration->fresh();
    }

    /**
     * Cancel a registration.
     */
    public function cancel(ProgramRegistration $registration): ProgramRegistration
    {
        $registration->update([
            'status' => RegistrationStatus::Cancelled,
        ]);

        return $registration->fresh();
    }

    /**
     * Mark an approved registration as completed, recording attendance and
     * score, and automatically issue a certificate if eligibility conditions
     * are met:
     *   - attendance_percentage >= 80
     *   - score, if provided, >= 60
     *
     * Certificate issuance is idempotent — calling this multiple times will
     * not produce duplicate certificates.
     *
     * @throws RegistrationNotApprovedException
     */
    public function markCompleted(
        ProgramRegistration $registration,
        User $admin,
        ?float $score = null,
        ?float $attendancePercentage = null,
    ): ProgramRegistration {
        if (! $registration->isApproved()) {
            throw new RegistrationNotApprovedException;
        }

        // Prefer the percentage calculated from daily attendance records.
        // Only fall back to the passed parameter (or stored value) when no
        // daily records exist yet.
        $calculatedPct = $registration->calculateAttendancePercentage();
        $finalPct = $calculatedPct ?? $attendancePercentage ?? $registration->attendance_percentage;

        $registration->update([
            'status' => RegistrationStatus::Completed,
            'score' => $score,
            'attendance_percentage' => $finalPct,
        ]);

        $registration->refresh();

        if ($registration->isEligibleForCertificate()) {
            $this->certificateService->issue(
                $registration->user,
                $registration->trainingProgram,
                $admin,
            );
        }

        $registration->loadMissing(['user', 'trainingProgram.learningPath']);
        $program = $registration->trainingProgram;
        if ($program !== null && $program->learning_path_id !== null) {
            $path = $program->learningPath;
            if ($path !== null) {
                $this->progressService->completePathIfEligible($registration->user, $path);
            }
        }

        return $registration;
    }
}
