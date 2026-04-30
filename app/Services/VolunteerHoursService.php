<?php

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Enums\VolunteerHoursStatus;
use App\Models\User;
use App\Models\VolunteerHour;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerRegistration;

class VolunteerHoursService
{
    public function __construct(
        private readonly VolunteerRegistrationService $registrationService,
        private readonly CertificateService           $certificateService,
    ) {}

    /**
     * Log a new volunteer hours entry with status = pending.
     */
    public function addHours(
        User $user,
        ?VolunteerOpportunity $opportunity,
        float $hours,
        User $admin,
    ): VolunteerHour {
        return VolunteerHour::create([
            'user_id'        => $user->id,
            'opportunity_id' => $opportunity?->id,
            'hours'          => $hours,
            'status'         => VolunteerHoursStatus::Pending,
        ]);
    }

    /**
     * Approve a pending hours entry and automatically mark the registration
     * as completed when the volunteer's total approved hours meet the requirement.
     */
    public function approveHours(VolunteerHour $record, User $admin): VolunteerHour
    {
        if ($record->status === VolunteerHoursStatus::Approved) {
            return $record;
        }

        $record->update([
            'status'      => VolunteerHoursStatus::Approved,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $this->checkAndCompleteRegistration($record, $admin);

        return $record->fresh();
    }

    /**
     * Reject a volunteer hours entry.
     */
    public function rejectHours(VolunteerHour $record, User $admin): VolunteerHour
    {
        if ($record->status === VolunteerHoursStatus::Rejected) {
            return $record;
        }

        $record->update([
            'status' => VolunteerHoursStatus::Rejected,
        ]);

        return $record->fresh();
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    /**
     * After approving an hours entry, recalculate the total for this user +
     * opportunity and mark the registration completed if the threshold is met.
     */
    private function checkAndCompleteRegistration(VolunteerHour $record, User $admin): void
    {
        if ($record->opportunity_id === null) {
            return;
        }

        /** @var VolunteerRegistration|null $registration */
        $registration = VolunteerRegistration::query()
            ->where('user_id', $record->user_id)
            ->where('opportunity_id', $record->opportunity_id)
            ->where('status', RegistrationStatus::Approved->value)
            ->first();

        if ($registration === null) {
            return;
        }

        $opportunity    = $registration->opportunity;
        $hoursExpected  = (float) $opportunity->hours_expected;

        if ($hoursExpected <= 0) {
            return;
        }

        $totalApproved = $registration->getApprovedHours();

        if ($totalApproved >= $hoursExpected) {
            $this->registrationService->markCompleted($registration, $admin);
            // Issue volunteer certificate (idempotent — no duplicate if already issued)
            $registration->loadMissing(['user', 'opportunity']);
            $this->certificateService->issue($registration->user, $registration->opportunity);
        }
    }
}
