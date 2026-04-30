<?php

namespace App\Services;

use App\Enums\OpportunityStatus;
use App\Enums\RegistrationStatus;
use App\Exceptions\DuplicateRegistrationException;
use App\Exceptions\OpportunityCapacityExceededException;
use App\Exceptions\OpportunityNotPublishedException;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;
use App\Notifications\VolunteerRegistrationApproved;
use App\Notifications\VolunteerRegistrationRejected;

class VolunteerRegistrationService
{
    public function __construct(
        private readonly EmailLogService $emailLogService,
    ) {}

    /**
     * Register a user for a volunteer opportunity.
     *
     * @throws OpportunityNotPublishedException
     * @throws DuplicateRegistrationException
     * @throws OpportunityCapacityExceededException
     */
    public function register(User $user, VolunteerOpportunity $opportunity): VolunteerRegistration
    {
        if ($opportunity->status !== OpportunityStatus::Published) {
            throw new OpportunityNotPublishedException();
        }

        $exists = VolunteerRegistration::where('opportunity_id', $opportunity->id)
            ->where('user_id', $user->id)
            ->whereNotIn('status', [
                RegistrationStatus::Rejected->value,
                RegistrationStatus::Cancelled->value,
            ])
            ->exists();

        if ($exists) {
            throw new DuplicateRegistrationException();
        }

        if (! $opportunity->hasCapacity()) {
            throw new OpportunityCapacityExceededException();
        }

        return VolunteerRegistration::create([
            'opportunity_id' => $opportunity->id,
            'user_id'        => $user->id,
            'status'         => RegistrationStatus::Pending,
        ]);
    }

    /**
     * Approve a pending volunteer registration.
     *
     * @throws OpportunityCapacityExceededException
     */
    public function approve(VolunteerRegistration $registration, User $approvedBy): VolunteerRegistration
    {
        if ($registration->status !== RegistrationStatus::Pending) {
            return $registration;
        }

        $opportunity = $registration->opportunity;

        if ($opportunity->capacity !== null) {
            $approvedCount = $opportunity->registrations()
                ->where('status', RegistrationStatus::Approved->value)
                ->count();

            if ($approvedCount >= $opportunity->capacity) {
                throw new OpportunityCapacityExceededException();
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
            notification: new VolunteerRegistrationApproved($registration),
            templateKey:  'volunteer_registration.approved',
            subject:      'Your Volunteer Registration Has Been Approved — ' . $opportunity->title,
            sentBy:       $approvedBy,
        );

        return $registration->fresh();
    }

    /**
     * Reject a volunteer registration with an optional reason.
     */
    public function reject(VolunteerRegistration $registration, User $rejectedBy, ?string $reason = null): VolunteerRegistration
    {
        $registration->update([
            'status'          => RegistrationStatus::Rejected,
            'rejected_reason' => $reason,
        ]);

        $registration->loadMissing(['user', 'opportunity']);

        $this->emailLogService->send(
            recipient:    $registration->user,
            notification: new VolunteerRegistrationRejected($registration),
            templateKey:  'volunteer_registration.rejected',
            subject:      'Volunteer Registration Update — ' . $registration->opportunity->title,
            sentBy:       $rejectedBy,
        );

        return $registration->fresh();
    }

    /**
     * Mark a registration as completed once the volunteer has met the hours requirement.
     * Only approved registrations can be completed.
     */
    public function markCompleted(VolunteerRegistration $registration, User $admin): VolunteerRegistration
    {
        if (! $registration->isApproved()) {
            return $registration;
        }

        $registration->update([
            'status' => RegistrationStatus::Completed,
        ]);

        return $registration->fresh();
    }

    /**
     * Cancel a volunteer registration.
     */
    public function cancel(VolunteerRegistration $registration): VolunteerRegistration
    {
        $registration->update([
            'status' => RegistrationStatus::Cancelled,
        ]);

        return $registration->fresh();
    }
}
