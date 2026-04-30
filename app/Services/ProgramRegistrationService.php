<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Exceptions\ProgramCapacityExceededException;
use App\Exceptions\RegistrationNotApprovedException;
use App\Exceptions\RegistrationWindowClosedException;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Notifications\ProgramRegistrationApproved;
use App\Notifications\ProgramRegistrationRejected;

class ProgramRegistrationService
{
    public function __construct(
        private readonly EmailLogService    $emailLogService,
        private readonly CertificateService $certificateService,
    ) {}

    /**
     * Register a user to a training program.
     *
     * @throws RegistrationWindowClosedException
     * @throws ProgramCapacityExceededException
     */
    public function register(TrainingProgram $program, User $user): ProgramRegistration
    {
        if (! $program->isRegistrationOpen()) {
            throw new RegistrationWindowClosedException();
        }

        // We do not check capacity at registration time — capacity is enforced on approval.
        // This allows registrations to be queued as pending even when the program is full,
        // to handle dropouts. Remove this comment if you want to block here too.

        return ProgramRegistration::firstOrCreate(
            [
                'training_program_id' => $program->id,
                'user_id'             => $user->id,
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

        $program = $registration->trainingProgram;

        if ($program->capacity !== null) {
            $approvedCount = $program->registrations()
                ->where('status', RegistrationStatus::Approved->value)
                ->count();

            if ($approvedCount >= $program->capacity) {
                throw new ProgramCapacityExceededException();
            }
        }

        $registration->update([
            'status'      => RegistrationStatus::Approved,
            'approved_by' => $approvedBy->id,
            'approved_at' => now(),
        ]);

        $registration->loadMissing('user');

        $this->emailLogService->send(
            recipient:    $registration->user,
            notification: new ProgramRegistrationApproved($registration),
            templateKey:  'program_registration.approved',
            subject:      'Your Registration Has Been Approved — ' . $program->title,
            sentBy:       $approvedBy,
        );

        return $registration->fresh();
    }

    /**
     * Reject a registration with an optional reason.
     */
    public function reject(ProgramRegistration $registration, ?string $reason = null): ProgramRegistration
    {
        $registration->update([
            'status'          => RegistrationStatus::Rejected,
            'rejected_reason' => $reason,
        ]);

        $registration->loadMissing(['user', 'trainingProgram']);

        $this->emailLogService->send(
            recipient:    $registration->user,
            notification: new ProgramRegistrationRejected($registration),
            templateKey:  'program_registration.rejected',
            subject:      'Registration Update — ' . $registration->trainingProgram->title,
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
        User                $admin,
        ?float              $score = null,
        ?float              $attendancePercentage = null,
    ): ProgramRegistration {
        if (! $registration->isApproved()) {
            throw new RegistrationNotApprovedException();
        }

        // Prefer the percentage calculated from daily attendance records.
        // Only fall back to the passed parameter (or stored value) when no
        // daily records exist yet.
        $calculatedPct = $registration->calculateAttendancePercentage();
        $finalPct      = $calculatedPct ?? $attendancePercentage ?? $registration->attendance_percentage;

        $registration->update([
            'status'                => RegistrationStatus::Completed,
            'score'                 => $score,
            'attendance_percentage' => $finalPct,
        ]);

        $registration->refresh();

        if ($registration->isEligibleForCertificate()) {
            $this->certificateService->issue(
                $registration->user,
                $registration->trainingProgram,
            );
        }

        return $registration;
    }
}
