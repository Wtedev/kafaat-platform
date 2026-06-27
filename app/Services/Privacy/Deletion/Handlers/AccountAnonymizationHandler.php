<?php

namespace App\Services\Privacy\Deletion\Handlers;

use App\Data\Privacy\DeletionExecutionContext;
use App\Enums\AccountStatus;
use App\Enums\DeletionHandlerName;
use App\Services\Privacy\Deletion\Contracts\DeletionHandlerInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class AccountAnonymizationHandler implements DeletionHandlerInterface
{
    public function name(): string
    {
        return DeletionHandlerName::AccountAnonymization->value;
    }

    public function handle(DeletionExecutionContext $context): void
    {
        $user = $context->target->fresh();

        if ($user === null) {
            return;
        }

        $anonymizedEmail = $this->anonymizedEmail($user->id);
        $displayName = (string) config('privacy_deletion.anonymized_display_name', 'مستخدم محذوف');

        $user->syncRoles([]);
        $user->syncPermissions([]);

        $user->forceFill([
            'name' => $displayName,
            'first_name' => null,
            'father_name' => null,
            'grandfather_name' => null,
            'family_name' => null,
            'email' => $anonymizedEmail,
            'phone' => null,
            'staff_photo' => null,
            'password' => Hash::make(Str::random(64)),
            'remember_token' => null,
            'is_active' => false,
            'notify_email' => false,
            'notification_settings' => null,
            'identity_type' => null,
            'identity_number_ciphertext' => null,
            'identity_number_lookup_hash' => null,
            'identity_number_last4' => null,
            'identity_confirmed_at' => null,
            'profile_completed_at' => null,
            'account_status' => AccountStatus::Anonymized,
            'privacy_deleted_at' => now(),
            'anonymized_at' => now(),
            'deletion_request_id' => $context->privacyRequest->id,
        ])->saveQuietly();
    }

    private function anonymizedEmail(int $userId): string
    {
        $domain = (string) config('privacy_deletion.anonymized_email_domain', 'invalid.local');

        return 'deleted-'.Str::uuid()->toString().'@'.$domain;
    }
}
