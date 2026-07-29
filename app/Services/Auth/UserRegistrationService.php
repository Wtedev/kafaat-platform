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
use App\Services\Rbac\RbacCatalog;
use Illuminate\Database\QueryException;
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

            $password = isset($data['password_hash']) && is_string($data['password_hash']) && $data['password_hash'] !== ''
                ? $data['password_hash']
                : Hash::make((string) $data['password']);

            $userAttributes = [
                'email' => $data['email'],
                'password' => $password,
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

            if (array_key_exists('email_verified_at', $data) && $data['email_verified_at'] !== null) {
                $userAttributes['email_verified_at'] = $data['email_verified_at'];
            }

            PersonNameService::syncCompatibilityName($userAttributes, $nameParts);

            $user = new User;
            $user->forceFill($userAttributes);

            try {
                $user->save();
            } catch (QueryException $exception) {
                if (IdentityNumberService::isLookupHashUniqueViolation($exception)) {
                    throw new InvalidArgumentException('duplicate_identity');
                }

                throw $exception;
            }

            $user->assignRole(RbacCatalog::ROLE_BENEFICIARY);

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
