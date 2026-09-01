<?php

namespace App\Services\Staff;

use App\Enums\SecurityLogResult;
use App\Enums\SecurityLogSeverity;
use App\Models\User;
use App\Services\Security\SecurityLogService;

final class StaffNotificationPreferenceService
{
    public function __construct(
        private readonly SecurityLogService $securityLogService,
    ) {}

    /**
     * Update the master email toggle without overwriting detailed category settings.
     *
     * @return bool Whether notify_email actually changed.
     */
    public function updateNotifyEmail(User $user, bool $wantsEmail): bool
    {
        $previous = (bool) $user->notify_email;

        if ($previous === $wantsEmail) {
            return false;
        }

        $attributes = ['notify_email' => $wantsEmail];

        if ($user->notification_prefs_set_at === null) {
            $attributes['notification_prefs_set_at'] = now();
        }

        $user->forceFill($attributes)->save();

        $this->securityLogService->record(
            $wantsEmail ? 'staff.notify_email_enabled' : 'staff.notify_email_disabled',
            SecurityLogResult::Success,
            SecurityLogSeverity::Info,
            $user,
            request: request(),
        );

        return true;
    }
}
