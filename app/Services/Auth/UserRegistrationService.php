<?php

namespace App\Services\Auth;

use App\Enums\IdentityType;
use App\Enums\PrivacyPolicyAcknowledgementSource;
use App\Models\PrivacyPolicyVersion;
use App\Models\User;
use App\Services\Identity\IdentityNumberService;
use App\Services\Identity\PersonNameService;
use App\Services\Identity\SaudiPhoneService;
use App\Services\Privacy\PrivacyPolicyAcknowledgementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class UserRegistrationService
{
    public function __construct(
        private readonly PrivacyPolicyAcknowledgementService $acknowledgementService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, PrivacyPolicyVersion $policy, ?Request $request = null): User
    {
        return DB::transaction(function () use ($data, $policy, $request): User {
            $nameParts = PersonNameService::normalizedParts($data);
            $identityType = IdentityType::from((string) $data['identity_type']);
            $identityPayload = IdentityNumberService::prepareStoragePayload(
                (string) $data['identity_number'],
                $identityType,
            );

            if (IdentityNumberService::isDuplicate((string) $data['identity_number'])) {
                throw new InvalidArgumentException('duplicate_identity');
            }

            $phone = SaudiPhoneService::normalize((string) $data['phone']);
            if ($phone === null) {
                throw new InvalidArgumentException('invalid_phone');
            }

            $userAttributes = [
                'email' => $data['email'],
                'password' => Hash::make((string) $data['password']),
                'role_type' => 'beneficiary',
                'is_active' => true,
                'phone' => $phone,
                ...$nameParts,
                'identity_type' => $identityPayload['identity_type']->value,
                'identity_number_ciphertext' => $identityPayload['identity_number_ciphertext'],
                'identity_number_lookup_hash' => $identityPayload['identity_number_lookup_hash'],
                'identity_number_last4' => $identityPayload['identity_number_last4'],
                'identity_confirmed_at' => $identityPayload['identity_confirmed_at'],
                'profile_completed_at' => now(),
            ];

            PersonNameService::syncCompatibilityName($userAttributes, $nameParts);

            $user = new User();
        $user->forceFill($userAttributes);
        $user->save();

            $user->assignRole('trainee');

            $user->profile()->create([
                'birth_date' => $data['birth_date'],
                'gender' => $data['gender'],
            ]);

            $this->acknowledgementService->acknowledge(
                $user,
                $policy,
                PrivacyPolicyAcknowledgementSource::Registration,
                $request,
            );

            return $user->fresh(['profile']);
        });
    }
}
