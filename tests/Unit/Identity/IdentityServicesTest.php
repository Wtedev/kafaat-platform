<?php

namespace Tests\Unit\Identity;

use App\Enums\IdentityType;
use App\Services\Identity\IdentityNumberService;
use App\Services\Identity\PersonNameService;
use App\Services\Identity\SaudiPhoneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\TestCase;

class IdentityServicesTest extends TestCase
{
    use GeneratesTestIdentityData;
    use RefreshDatabase;

    public function test_normalizes_spaces_in_arabic_name(): void
    {
        $this->assertSame('أحمد محمد', PersonNameService::normalizePart('  أحمد   محمد  '));
    }

    public function test_rejects_digits_in_name(): void
    {
        $this->assertFalse(PersonNameService::isValidPart('أحمد123'));
    }

    public function test_builds_full_name_from_parts(): void
    {
        $full = PersonNameService::buildFullName([
            'first_name' => 'أحمد',
            'father_name' => 'محمد',
            'grandfather_name' => 'عبدالله',
            'family_name' => 'السعود',
        ]);

        $this->assertSame('أحمد محمد عبدالله السعود', $full);
    }

    public function test_normalizes_arabic_identity_digits(): void
    {
        $normalized = IdentityNumberService::normalize('١٠١٠١٠١٠١٠');
        $this->assertSame('1010101010', $normalized);
    }

    public function test_accepts_any_ten_digit_identity_without_checksum(): void
    {
        $this->assertTrue(IdentityNumberService::isValidFormat('1234567890'));
        $this->assertTrue(IdentityNumberService::isValidForType('9876543210', IdentityType::NationalId));
        $this->assertFalse(IdentityNumberService::isValidFormat('123456789'));
        $this->assertFalse(IdentityNumberService::isValidFormat('12345678901'));
        $this->assertFalse(IdentityNumberService::isValidFormat('abcdefghij'));
    }

    public function test_prepare_storage_does_not_store_plaintext(): void
    {
        $raw = $this->generateValidNationalId();
        $payload = IdentityNumberService::prepareStoragePayload($raw, IdentityType::NationalId);

        $this->assertNotSame($raw, $payload['identity_number_ciphertext']);
        $this->assertSame(substr($raw, -4), $payload['identity_number_last4']);
    }

    public function test_lookup_hash_is_stable(): void
    {
        $raw = $this->generateValidNationalId();

        $this->assertSame(
            IdentityNumberService::generateLookupHash($raw),
            IdentityNumberService::generateLookupHash($raw),
        );
    }

    public function test_is_duplicate_detects_existing_lookup_hash(): void
    {
        $identity = $this->generateValidNationalId();
        $payload = IdentityNumberService::prepareStoragePayload($identity, IdentityType::NationalId);

        $user = \App\Models\User::factory()->create([
            'identity_type' => $payload['identity_type']->value,
            'identity_number_ciphertext' => $payload['identity_number_ciphertext'],
            'identity_number_lookup_hash' => $payload['identity_number_lookup_hash'],
            'identity_number_last4' => $payload['identity_number_last4'],
        ]);

        $this->assertTrue(IdentityNumberService::isDuplicate($identity));
        $this->assertFalse(IdentityNumberService::isDuplicate($identity, $user->id));
        $this->assertFalse(IdentityNumberService::isDuplicate($this->generateValidNationalId()));
    }

    public function test_masking_format(): void
    {
        $this->assertSame('******1234', IdentityNumberService::mask('1234'));
    }

    public function test_normalizes_saudi_mobile_formats(): void
    {
        $this->assertSame('+966501234567', SaudiPhoneService::normalize('0501234567'));
        $this->assertSame('+966501234567', SaudiPhoneService::normalize('+966501234567'));
    }
}
