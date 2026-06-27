<?php

namespace Tests\Concerns;

use App\Enums\IdentityType;
use App\Services\Identity\IdentityNumberService;

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
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $nine = $type->expectedFirstDigit().str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            $check = $this->saudiIdentityCheckDigit($nine);
            $candidate = $nine.$check;

            if (IdentityNumberService::isValidForType($candidate, $type)) {
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
            'email' => 'user'.uniqid('', true).'@example.com',
            'phone' => '0501234567',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ], $overrides);
    }

    private function saudiIdentityCheckDigit(string $firstNine): int
    {
        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $digit = (int) $firstNine[$i];

            if ($i % 2 === 0) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return (10 - ($sum % 10)) % 10;
    }
}
