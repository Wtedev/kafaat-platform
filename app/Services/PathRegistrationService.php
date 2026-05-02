<?php

namespace App\Services;

use App\Enums\PathStatus;
use App\Enums\RegistrationStatus;
use App\Exceptions\DuplicateRegistrationException;
use App\Exceptions\PathCapacityExceededException;
use App\Exceptions\PathNotPublishedException;
use App\Models\LearningPath;
use App\Models\PathRegistration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Notifications\PathRegistrationApproved;
use App\Notifications\PathRegistrationRejected;
use App\Services\Inbox\InboxNotificationService;

class PathRegistrationService
{
    public function __construct(
        private readonly EmailLogService $emailLogService,
        private readonly InboxNotificationService $inboxNotifications,
        private readonly ProgramRegistrationService $programRegistrationService,
    ) {}

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Register a user for a learning path.
     *
     * @throws PathNotPublishedException
     * @throws DuplicateRegistrationException
     * @throws PathCapacityExceededException
     */
    public function register(User $user, LearningPath $path): PathRegistration
    {
        // 1. Path must be published
        if ($path->status !== PathStatus::Published) {
            throw new PathNotPublishedException;
        }

        // 2. Prevent duplicate active registrations (ignore rejected/cancelled)
        $exists = PathRegistration::where('learning_path_id', $path->id)
            ->where('user_id', $user->id)
            ->whereNotIn('status', [
                RegistrationStatus::Rejected->value,
                RegistrationStatus::Cancelled->value,
            ])
            ->exists();

        if ($exists) {
            throw new DuplicateRegistrationException;
        }

        // 3. Capacity check — only count approved registrations
        if ($path->capacity !== null && $this->getApprovedCount($path) >= $path->capacity) {
            throw new PathCapacityExceededException;
        }

        return PathRegistration::create([
            'learning_path_id' => $path->id,
            'user_id' => $user->id,
            'status' => RegistrationStatus::Pending,
        ]);
    }

    /**
     * Approve a pending registration.
     *
     * @throws PathCapacityExceededException
     */
    public function approve(PathRegistration $registration, User $approvedBy): PathRegistration
    {
        if ($registration->status === RegistrationStatus::Approved) {
            return $registration;
        }

        $path = $registration->learningPath;

        if ($path->capacity !== null) {
            if ($this->getApprovedCount($path) >= $path->capacity) {
                throw new PathCapacityExceededException;
            }
        }

        $registration->loadMissing('user');

        $newlyApprovedProgramRegs = [];

        DB::transaction(function () use ($registration, $approvedBy, $path, &$newlyApprovedProgramRegs): void {
            $registration->update([
                'status' => RegistrationStatus::Approved,
                'approved_by' => $approvedBy->id,
                'approved_at' => now(),
            ]);

            $newlyApprovedProgramRegs = $this->programRegistrationService->approveAllProgramsForPathMemberWithoutNotifications(
                $path,
                $registration->user,
                $approvedBy,
            );
        });

        $registration->loadMissing('user');

        $this->emailLogService->send(
            recipient: $registration->user,
            notification: new PathRegistrationApproved($registration),
            templateKey: 'path_registration.approved',
            subject: 'Your Registration Has Been Approved — '.$path->title,
            sentBy: $approvedBy,
        );

        $this->inboxNotifications->registrationApprovedPath($registration->user, $path, $approvedBy);

        foreach ($newlyApprovedProgramRegs as $programRegistration) {
            $this->programRegistrationService->sendProgramRegistrationApprovedNotifications(
                $programRegistration->fresh(),
                $approvedBy,
            );
        }

        return $registration->fresh();
    }

    /**
     * Reject a registration with an optional reason.
     */
    public function reject(PathRegistration $registration, ?string $reason = null): PathRegistration
    {
        $registration->update([
            'status' => RegistrationStatus::Rejected,
            'rejected_reason' => $reason,
        ]);

        $registration->loadMissing(['user', 'learningPath']);

        $this->emailLogService->send(
            recipient: $registration->user,
            notification: new PathRegistrationRejected($registration),
            templateKey: 'path_registration.rejected',
            subject: 'Registration Update — '.$registration->learningPath->title,
        );

        $rejector = auth()->user();
        $this->inboxNotifications->registrationRejectedPath(
            $registration->user,
            $registration->learningPath,
            $reason,
            $rejector instanceof User ? $rejector : null,
        );

        return $registration->fresh();
    }

    /**
     * Cancel a registration (by the registrant or an admin).
     */
    public function cancel(PathRegistration $registration): PathRegistration
    {
        $registration->update([
            'status' => RegistrationStatus::Cancelled,
        ]);

        return $registration->fresh();
    }

    /**
     * Manually mark a registration as completed (admin override).
     * Does not check course progress — admin decision.
     */
    public function complete(PathRegistration $registration): PathRegistration
    {
        $registration->update([
            'status' => RegistrationStatus::Completed,
            'completed_at' => $registration->completed_at ?? now(),
        ]);

        return $registration->fresh();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Count currently approved registrations for a learning path.
     * Used to enforce capacity at both registration and approval time.
     */
    public function getApprovedCount(LearningPath $path): int
    {
        return $path->registrations()
            ->where('status', RegistrationStatus::Approved->value)
            ->count();
    }
}
