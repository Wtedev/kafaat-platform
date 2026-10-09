<?php

namespace Tests\Feature\Identity;

use App\Enums\IdentityCategory;
use App\Enums\IdentityType;
use App\Models\User;
use App\Services\Identity\IdentityNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class IdentityBackfillCategoryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_counts_without_writing_or_printing_numbers(): void
    {
        $saudiNumber = '1099887711';
        $residentNumber = '2099887722';
        $invalidNumber = '3099887733';

        $saudi = User::factory()->create([
            'identity_type' => IdentityType::Iqama, // deliberate mismatch
            'identity_category' => null,
            'identity_number_ciphertext' => IdentityNumberService::encrypt($saudiNumber),
            'identity_number_lookup_hash' => IdentityNumberService::generateLookupHash($saudiNumber),
            'identity_number_last4' => '7711',
        ]);

        User::factory()->create([
            'identity_type' => IdentityType::Iqama,
            'identity_category' => null,
            'identity_number_ciphertext' => IdentityNumberService::encrypt($residentNumber),
            'identity_number_lookup_hash' => IdentityNumberService::generateLookupHash($residentNumber),
            'identity_number_last4' => '7722',
        ]);

        User::factory()->create([
            'identity_type' => IdentityType::NationalId,
            'identity_category' => null,
            'identity_number_ciphertext' => IdentityNumberService::encrypt($invalidNumber),
            'identity_number_lookup_hash' => IdentityNumberService::generateLookupHash($invalidNumber),
            'identity_number_last4' => '7733',
        ]);

        User::factory()->create([
            'identity_type' => null,
            'identity_category' => null,
            'identity_number_ciphertext' => null,
            'identity_number_lookup_hash' => null,
            'identity_number_last4' => null,
        ]);

        $this->artisan('identity:backfill-category', ['--dry-run' => true])
            ->expectsOutputToContain('saudi')
            ->expectsOutputToContain('resident')
            ->expectsOutputToContain('no_number')
            ->expectsOutputToContain('type_mismatch')
            ->expectsOutputToContain('invalid_prefix')
            ->doesntExpectOutputToContain($saudiNumber)
            ->doesntExpectOutputToContain($residentNumber)
            ->doesntExpectOutputToContain($invalidNumber)
            ->assertSuccessful();

        $saudi->refresh();
        $this->assertNull($saudi->identity_category);
        $this->assertSame(IdentityType::Iqama, $saudi->identity_type);
    }

    public function test_write_mode_updates_category_and_type_but_skips_invalid_prefix(): void
    {
        $saudiNumber = '1099887744';
        $invalidNumber = '8099887755';

        $saudi = User::factory()->create([
            'identity_type' => IdentityType::Iqama,
            'identity_category' => null,
            'identity_number_ciphertext' => Crypt::encryptString($saudiNumber),
            'identity_number_lookup_hash' => IdentityNumberService::generateLookupHash($saudiNumber),
            'identity_number_last4' => '7744',
        ]);

        $invalid = User::factory()->create([
            'identity_type' => IdentityType::NationalId,
            'identity_category' => null,
            'identity_number_ciphertext' => Crypt::encryptString($invalidNumber),
            'identity_number_lookup_hash' => IdentityNumberService::generateLookupHash($invalidNumber),
            'identity_number_last4' => '7755',
        ]);

        $this->artisan('identity:backfill-category')
            ->doesntExpectOutputToContain($saudiNumber)
            ->doesntExpectOutputToContain($invalidNumber)
            ->assertSuccessful();

        $saudi->refresh();
        $invalid->refresh();

        $this->assertSame(IdentityCategory::Saudi, $saudi->identity_category);
        $this->assertSame(IdentityType::NationalId, $saudi->identity_type);
        $this->assertNull($invalid->identity_category);
        $this->assertSame(IdentityType::NationalId, $invalid->identity_type);
    }
}
