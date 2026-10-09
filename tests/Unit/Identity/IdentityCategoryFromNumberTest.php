<?php

namespace Tests\Unit\Identity;

use App\Enums\IdentityCategory;
use App\Enums\IdentityType;
use App\Services\Identity\IdentityNumberService;
use Tests\TestCase;

class IdentityCategoryFromNumberTest extends TestCase
{
    public function test_category_from_number_maps_prefix(): void
    {
        $this->assertSame(IdentityCategory::Saudi, IdentityNumberService::categoryFromNumber('1099887766'));
        $this->assertSame(IdentityCategory::Resident, IdentityNumberService::categoryFromNumber('2099887766'));
        $this->assertNull(IdentityNumberService::categoryFromNumber('3099887766'));
        $this->assertNull(IdentityNumberService::categoryFromNumber('109988776'));
        $this->assertNull(IdentityNumberService::categoryFromNumber(null));
    }

    public function test_rejects_numbers_not_starting_with_one_or_two(): void
    {
        $this->assertFalse(IdentityNumberService::isValidFormat('3099887766'));
        $this->assertFalse(IdentityNumberService::isValidFormat('0099887766'));
        $this->assertSame(
            IdentityNumberService::INVALID_PREFIX_MESSAGE,
            IdentityNumberService::validationMessage('3099887766'),
        );
    }

    public function test_prepare_storage_sets_type_and_category_from_number(): void
    {
        $saudi = IdentityNumberService::prepareStoragePayload('1099887766');
        $this->assertSame(IdentityCategory::Saudi, $saudi['identity_category']);
        $this->assertSame(IdentityType::NationalId, $saudi['identity_type']);

        $resident = IdentityNumberService::prepareStoragePayload('2099887766');
        $this->assertSame(IdentityCategory::Resident, $resident['identity_category']);
        $this->assertSame(IdentityType::Iqama, $resident['identity_type']);
    }

    public function test_prepare_storage_rejects_mismatched_explicit_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        IdentityNumberService::prepareStoragePayload('1099887766', IdentityType::Iqama);
    }
}
