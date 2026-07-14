<?php

namespace Tests\Unit\Identity;

use App\Enums\IdentityType;
use App\Models\User;
use App\Rules\UniqueIdentityLookupHash;
use App\Services\Identity\IdentityNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\TestCase;

class IdentityLookupUniquenessTest extends TestCase
{
    use GeneratesTestIdentityData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        IdentityNumberService::resetLookupKeyFallbackNotice();
    }

    public function test_missing_lookup_key_falls_back_and_detects_duplicates(): void
    {
        config(['identity.lookup_key' => null]);
        Log::spy();

        $identity = $this->generateValidNationalId();
        $payload = IdentityNumberService::prepareStoragePayload($identity, IdentityType::NationalId);

        User::factory()->create([
            'identity_type' => $payload['identity_type']->value,
            'identity_number_ciphertext' => $payload['identity_number_ciphertext'],
            'identity_number_lookup_hash' => $payload['identity_number_lookup_hash'],
            'identity_number_last4' => $payload['identity_number_last4'],
        ]);

        $this->assertFalse(IdentityNumberService::hasDedicatedLookupKey());
        $this->assertTrue(IdentityNumberService::isDuplicate($identity));
        $this->assertFalse(IdentityNumberService::isDuplicate($this->generateValidNationalId()));

        Log::shouldHaveReceived('critical')
            ->once()
            ->withArgs(fn (string $message): bool => str_contains($message, 'IDENTITY_LOOKUP_KEY'));
    }

    public function test_store_and_duplicate_check_use_same_normalization(): void
    {
        $identity = '١١٤٤٩٢٩٠٣٢';
        $payload = IdentityNumberService::prepareStoragePayload($identity, IdentityType::NationalId);

        User::factory()->create([
            'identity_type' => $payload['identity_type']->value,
            'identity_number_ciphertext' => $payload['identity_number_ciphertext'],
            'identity_number_lookup_hash' => $payload['identity_number_lookup_hash'],
            'identity_number_last4' => $payload['identity_number_last4'],
        ]);

        $this->assertSame('1144929032', IdentityNumberService::normalize($identity));
        $this->assertTrue(IdentityNumberService::isDuplicate('1144929032'));
        $this->assertTrue(IdentityNumberService::isDuplicate($identity));
        $this->assertSame(
            $payload['identity_number_lookup_hash'],
            IdentityNumberService::generateLookupHash('1144929032'),
        );
    }

    public function test_unique_rule_rejects_duplicate_with_arabic_message(): void
    {
        $identity = '1144929032';
        $payload = IdentityNumberService::prepareStoragePayload($identity, IdentityType::NationalId);

        User::factory()->create([
            'identity_type' => $payload['identity_type']->value,
            'identity_number_ciphertext' => $payload['identity_number_ciphertext'],
            'identity_number_lookup_hash' => $payload['identity_number_lookup_hash'],
            'identity_number_last4' => $payload['identity_number_last4'],
        ]);

        $validator = Validator::make(
            ['identity_number' => $identity],
            ['identity_number' => [new UniqueIdentityLookupHash(IdentityType::NationalId)]],
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            IdentityNumberService::DUPLICATE_MESSAGE,
            $validator->errors()->first('identity_number'),
        );
    }

    public function test_unique_rule_passes_for_available_identity(): void
    {
        $validator = Validator::make(
            ['identity_number' => '1144929032'],
            ['identity_number' => [new UniqueIdentityLookupHash(IdentityType::NationalId)]],
        );

        $this->assertFalse($validator->fails());
    }

    public function test_unique_rule_passes_when_lookup_key_missing_via_fallback(): void
    {
        config(['identity.lookup_key' => '']);
        Log::spy();

        $validator = Validator::make(
            ['identity_number' => '1144929032'],
            ['identity_number' => [new UniqueIdentityLookupHash(IdentityType::NationalId)]],
        );

        $this->assertFalse($validator->fails());
        $this->assertNotSame(
            IdentityNumberService::AVAILABILITY_CHECK_FAILED_MESSAGE,
            $validator->errors()->first('identity_number'),
        );
    }
}
