<?php

namespace Tests\Feature\Identity;

use App\Enums\IdentityCategory;
use App\Enums\IdentityType;
use App\Enums\ProfileGender;
use App\Models\User;
use App\Rules\ValidIdentityNumber;
use App\Services\Auth\UserRegistrationService;
use App\Services\Identity\IdentityNumberService;
use App\Services\Privacy\PrivacyPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\SeedsActivePrivacyPolicy;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class IdentityCategoryRegistrationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsActivePrivacyPolicy;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        $this->seedActivePrivacyPolicy();
    }

    public function test_registration_service_derives_saudi_category_from_number(): void
    {
        $user = $this->registerViaService('1099887766');

        $this->assertSame(IdentityCategory::Saudi, $user->identity_category);
        $this->assertSame(IdentityType::NationalId, $user->identity_type);
    }

    public function test_registration_service_derives_resident_category_from_number(): void
    {
        $user = $this->registerViaService('2099887766');

        $this->assertSame(IdentityCategory::Resident, $user->identity_category);
        $this->assertSame(IdentityType::Iqama, $user->identity_type);
    }

    public function test_valid_identity_number_rule_rejects_invalid_prefix(): void
    {
        $validator = Validator::make(
            ['identity_number' => '3099887766'],
            ['identity_number' => [new ValidIdentityNumber]],
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            IdentityNumberService::INVALID_PREFIX_MESSAGE,
            $validator->errors()->first('identity_number'),
        );
        $this->assertSame(0, User::query()->count());
    }

    private function registerViaService(string $identityNumber): User
    {
        $policy = PrivacyPolicyService::activeOrFail();

        return app(UserRegistrationService::class)->register([
            'first_name' => 'أحمد',
            'father_name' => 'محمد',
            'grandfather_name' => 'عبدالله',
            'family_name' => 'السعود',
            'identity_number' => $identityNumber,
            'birth_date' => '1995-05-15',
            'gender' => ProfileGender::Male->value,
            'email' => 'cat-'.uniqid('', true).'@example.com',
            'phone' => '0501234567',
            'password' => 'SecurePass1!',
            'email_verified_at' => now(),
        ], $policy);
    }
}
