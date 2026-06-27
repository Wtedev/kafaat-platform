<?php

namespace App\Services\Identity;

use App\Enums\IdentityType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UserProfileCompletionService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function complete(User $user, array $data, bool $requireIdentity = true): User
    {
        return DB::transaction(function () use ($user, $data, $requireIdentity): User {
            $nameParts = PersonNameService::normalizedParts($data);

            $phone = SaudiPhoneService::normalize((string) ($data['phone'] ?? ''));
            if ($phone === null) {
                throw new InvalidArgumentException('invalid_phone');
            }

            $userAttributes = [
                ...$nameParts,
                'phone' => $phone,
            ];

            PersonNameService::syncCompatibilityName($userAttributes, $nameParts);

            $hasIdentity = filled($user->identity_number_lookup_hash);

            if (! $hasIdentity && $requireIdentity) {
                if (empty($data['identity_type']) || empty($data['identity_number'])) {
                    throw new InvalidArgumentException('identity_required');
                }

                $identityType = IdentityType::from((string) $data['identity_type']);

                if (IdentityNumberService::isDuplicate((string) $data['identity_number'], $user->id)) {
                    throw new InvalidArgumentException('duplicate_identity');
                }

                $identityPayload = IdentityNumberService::prepareStoragePayload(
                    (string) $data['identity_number'],
                    $identityType,
                );

                $userAttributes = array_merge($userAttributes, [
                    'identity_type' => $identityPayload['identity_type']->value,
                    'identity_number_ciphertext' => $identityPayload['identity_number_ciphertext'],
                    'identity_number_lookup_hash' => $identityPayload['identity_number_lookup_hash'],
                    'identity_number_last4' => $identityPayload['identity_number_last4'],
                    'identity_confirmed_at' => $identityPayload['identity_confirmed_at'],
                ]);
            }

            $user->forceFill($userAttributes);

            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                ['birth_date' => $data['birth_date']],
            );

            $user->load('profile');

            if ($user->hasCompletedRequiredIdentityData()) {
                $user->profile_completed_at = now();
            }

            $user->save();

            return $user->fresh(['profile']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $nameParts = PersonNameService::normalizedParts($data);
            $phone = SaudiPhoneService::normalize((string) ($data['phone'] ?? ''));

            if ($phone === null) {
                throw new InvalidArgumentException('invalid_phone');
            }

            $userAttributes = [
                ...$nameParts,
                'phone' => $phone,
            ];

            PersonNameService::syncCompatibilityName($userAttributes, $nameParts);

            if (! filled($user->identity_number_lookup_hash)) {
                if (! empty($data['identity_type']) && ! empty($data['identity_number'])) {
                    $identityType = IdentityType::from((string) $data['identity_type']);

                    if (IdentityNumberService::isDuplicate((string) $data['identity_number'], $user->id)) {
                        throw new InvalidArgumentException('duplicate_identity');
                    }

                    $identityPayload = IdentityNumberService::prepareStoragePayload(
                        (string) $data['identity_number'],
                        $identityType,
                    );

                    $userAttributes = array_merge($userAttributes, [
                        'identity_type' => $identityPayload['identity_type']->value,
                        'identity_number_ciphertext' => $identityPayload['identity_number_ciphertext'],
                        'identity_number_lookup_hash' => $identityPayload['identity_number_lookup_hash'],
                        'identity_number_last4' => $identityPayload['identity_number_last4'],
                        'identity_confirmed_at' => $identityPayload['identity_confirmed_at'],
                    ]);
                }
            }

            $user->forceFill($userAttributes);

            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'city' => $data['city'] ?? null,
                    'job_title' => filled($data['job_title'] ?? null)
                        ? trim((string) $data['job_title'])
                        : null,
                    'birth_date' => $data['birth_date'] ?? $user->profile?->birth_date,
                ],
            );

            $user->load('profile');

            if ($user->hasCompletedRequiredIdentityData()) {
                $user->profile_completed_at = $user->profile_completed_at ?? now();
            }

            $user->save();

            return $user->fresh(['profile']);
        });
    }
}
