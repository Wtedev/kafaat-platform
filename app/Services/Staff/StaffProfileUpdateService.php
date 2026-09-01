<?php

namespace App\Services\Staff;

use App\Enums\SecurityLogResult;
use App\Enums\SecurityLogSeverity;
use App\Models\User;
use App\Rules\ValidSaudiMobile;
use App\Services\Identity\SaudiPhoneService;
use App\Services\Media\PublicMediaLifecycleService;
use App\Services\Security\SecurityLogService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

final class StaffProfileUpdateService
{
    public function __construct(
        private readonly PublicMediaLifecycleService $mediaLifecycle,
        private readonly StaffNotificationPreferenceService $notificationPreferences,
        private readonly SecurityLogService $securityLogService,
    ) {}

    /**
     * Persist non-sensitive staff profile fields (name, phone, photo, notify_email).
     *
     * Staff accounts use `users.name` only — structured name parts are for beneficiaries.
     *
     * @param  array{name?: mixed, phone?: mixed, staff_photo?: mixed, notify_email?: mixed}  $input
     */
    public function update(User $user, array $input): void
    {
        $input = array_intersect_key($input, array_flip(['name', 'phone', 'staff_photo', 'notify_email']));

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'الاسم مطلوب.',
            ]);
        }

        $phoneRaw = $input['phone'] ?? null;
        $phone = null;
        if ($phoneRaw !== null && $phoneRaw !== '') {
            Validator::make(
                ['phone' => $phoneRaw],
                ['phone' => [new ValidSaudiMobile(required: false)]],
            )->validate();

            $phone = SaudiPhoneService::normalize((string) $phoneRaw);
        }

        $notifyEmail = (bool) ($input['notify_email'] ?? false);

        $previousStaffPhoto = $user->staff_photo;
        $newStaffPhoto = isset($input['staff_photo']) && $input['staff_photo'] !== '' && $input['staff_photo'] !== null
            ? (string) $input['staff_photo']
            : null;

        $changedFields = [];
        if ($user->name !== $name) {
            $changedFields[] = 'name';
        }
        if ($user->phone !== $phone) {
            $changedFields[] = 'phone';
        }
        if ($user->staff_photo !== $newStaffPhoto) {
            $changedFields[] = 'staff_photo';
        }

        $user->name = $name;
        $user->phone = $phone;
        $user->staff_photo = $newStaffPhoto;

        try {
            $user->save();
        } catch (Throwable $e) {
            if (is_string($newStaffPhoto) && $newStaffPhoto !== $previousStaffPhoto) {
                $this->mediaLifecycle->discardFailedUpload($newStaffPhoto);
            }

            throw $e;
        }

        $this->mediaLifecycle->deleteOwnedIfReplaced($previousStaffPhoto, $user->staff_photo);

        $this->notificationPreferences->updateNotifyEmail($user, $notifyEmail);

        if ($changedFields !== []) {
            $this->securityLogService->record(
                'staff.profile_updated',
                SecurityLogResult::Success,
                SecurityLogSeverity::Info,
                $user,
                metadata: ['fields' => $changedFields],
                request: request(),
            );
        }
    }
}
