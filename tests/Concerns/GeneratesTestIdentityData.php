<?php

namespace Tests\Concerns;

use App\Enums\IdentityType;
use App\Services\Identity\IdentityNumberService;
use App\Services\Privacy\PrivacyPolicyService;

trait GeneratesTestIdentityData
{
    protected function generateValidNationalId(): string
    {
        return $this->generateValidIdentityForType(IdentityType::NationalId);
    }

    protected function generateValidIqama(): string
    {
        return $this->generateValidIdentityForType(IdentityType::Iqama);
    }

    protected function generateValidIdentityForType(IdentityType $type): string
    {
        // Any 10-digit number is accepted; type is stored separately without prefix/checksum rules.
        $prefix = $type === IdentityType::NationalId ? '1' : '2';

        for ($attempt = 0; $attempt < 200; $attempt++) {
            $candidate = $prefix.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);

            if (IdentityNumberService::isValidFormat($candidate)) {
                return $candidate;
            }
        }

        $this->fail('Unable to generate valid test identity number.');
    }

    /**
     * @return array<string, string>
     */
    protected function validRegistrationPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'أحمد',
            'father_name' => 'محمد',
            'grandfather_name' => 'عبدالله',
            'family_name' => 'السعود',
            'identity_type' => IdentityType::NationalId->value,
            'identity_number' => $this->generateValidNationalId(),
            'birth_date' => '1995-05-15',
            'gender' => 'male',
            'email' => 'user'.uniqid('', true).'@example.com',
            'phone' => '0501234567',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
            'privacy_policy_version' => PrivacyPolicyService::active()?->version ?? '1.0',
            'privacy_policy_acknowledged' => '1',
        ], $overrides);
    }
}
