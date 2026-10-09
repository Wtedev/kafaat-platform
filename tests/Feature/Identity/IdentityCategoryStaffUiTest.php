<?php

namespace Tests\Feature\Identity;

use App\Enums\IdentityCategory;
use App\Enums\IdentityType;
use App\Enums\ProfileGender;
use App\Models\User;
use App\Services\Identity\IdentityNumberService;
use App\Services\Rbac\RbacCatalog;
use App\Support\Exports\BeneficiaryProfileExportColumns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class IdentityCategoryStaffUiTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        config([
            'staff_ui.maintenance' => false,
            'staff_ui.ready_modules' => ['users'],
        ]);
    }

    public function test_beneficiaries_filter_by_identity_category(): void
    {
        $staff = $this->staff(['beneficiaries.view_basic', 'beneficiaries.view_contact']);

        $saudi = $this->makeBeneficiary(
            'saudi.filter@example.com',
            'السعودية',
            '1099112233',
            IdentityCategory::Saudi,
        );
        $resident = $this->makeBeneficiary(
            'resident.filter@example.com',
            'المقيمة',
            '2099112233',
            IdentityCategory::Resident,
        );

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.users.index', ['identity_category' => 'saudi']))
            ->assertOk()
            ->assertSee('السعودية')
            ->assertDontSee('المقيمة');

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.users.index', ['identity_category' => 'resident']))
            ->assertOk()
            ->assertSee('المقيمة')
            ->assertDontSee('السعودية');
    }

    public function test_invalid_identity_filter_is_admin_only_and_does_not_change_stored_numbers(): void
    {
        $admin = $this->admin();
        $staff = $this->staff(['beneficiaries.view_basic', 'beneficiaries.view_contact']);
        $saudi = $this->makeBeneficiary(
            'saudi.invalid-filter@example.com',
            'السعودية',
            '1099445566',
            IdentityCategory::Saudi,
        );
        $invalid = $this->invalidBeneficiary();
        $originalHash = $invalid->identity_number_lookup_hash;
        $originalType = $invalid->identity_type?->value;

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.users.index'))
            ->assertOk()
            ->assertSee('رقم هوية غير صالح');

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.users.index'))
            ->assertOk()
            ->assertDontSee('رقم هوية غير صالح');

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.users.index', ['identity_category' => 'invalid']))
            ->assertOk()
            ->assertSee('المرفوضة')
            ->assertDontSee('السعودية');

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.users.index', ['identity_category' => 'invalid']))
            ->assertOk()
            ->assertSee('السعودية')
            ->assertSee('المرفوضة');

        $invalid->refresh();
        $this->assertSame($originalHash, $invalid->identity_number_lookup_hash);
        $this->assertSame($originalType, $invalid->identity_type?->value);
        $this->assertNull($invalid->identity_category);
        $this->assertNotNull($saudi->identity_category);
    }

    public function test_beneficiary_profile_shows_category_without_full_number(): void
    {
        $staff = $this->staff([
            'beneficiaries.view_basic',
            'beneficiaries.identity.view_masked',
        ]);
        $beneficiary = $this->makeBeneficiary(
            'profile.cat@example.com',
            'الملف',
            '1099223344',
            IdentityCategory::Saudi,
        );

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.users.show', $beneficiary))
            ->assertOk()
            ->assertSee('الجنسية')
            ->assertSee('سعودي')
            ->assertDontSee('1099223344');
    }

    public function test_export_column_resolves_nationality_label_without_number(): void
    {
        $beneficiary = $this->makeBeneficiary(
            'export.cat@example.com',
            'التصدير',
            '2099334455',
            IdentityCategory::Resident,
        );

        $value = BeneficiaryProfileExportColumns::resolve($beneficiary->profile, 'identity_category');
        $this->assertSame('مقيم', $value);
        $this->assertStringNotContainsString('2099334455', (string) $value);
    }

    private function admin(): User
    {
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(RbacCatalog::ROLE_ADMIN);

        return $admin->fresh();
    }

    private function invalidBeneficiary(): User
    {
        $digits = '3099556677';
        $user = User::factory()->create([
            'name' => 'نورة سعد محمد المرفوضة',
            'first_name' => 'نورة',
            'father_name' => 'سعد',
            'grandfather_name' => 'محمد',
            'family_name' => 'المرفوضة',
            'email' => 'invalid.filter@example.com',
            'phone' => '0555001122',
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
            'identity_category' => null,
            'identity_type' => IdentityType::NationalId,
            'identity_number_ciphertext' => IdentityNumberService::encrypt($digits),
            'identity_number_lookup_hash' => IdentityNumberService::generateLookupHash($digits),
            'identity_number_last4' => '6677',
            'identity_confirmed_at' => now(),
        ]);
        $user->assignRole(RbacCatalog::ROLE_BENEFICIARY);

        return $user->fresh();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function staff(array $permissions = []): User
    {
        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole(RbacCatalog::ROLE_STAFF);
        if ($permissions !== []) {
            $staff->givePermissionTo($permissions);
        }

        return $staff->fresh();
    }

    private function makeBeneficiary(
        string $email,
        string $familyName,
        string $identity,
        IdentityCategory $category,
    ): User {
        $payload = IdentityNumberService::prepareStoragePayload($identity);

        $user = User::factory()->create([
            'name' => 'نورة سعد محمد '.$familyName,
            'first_name' => 'نورة',
            'father_name' => 'سعد',
            'grandfather_name' => 'محمد',
            'family_name' => $familyName,
            'email' => $email,
            'phone' => '0555'.substr($identity, -6),
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
            'identity_category' => $category,
            'identity_type' => $payload['identity_type'],
            'identity_number_ciphertext' => $payload['identity_number_ciphertext'],
            'identity_number_lookup_hash' => $payload['identity_number_lookup_hash'],
            'identity_number_last4' => $payload['identity_number_last4'],
            'identity_confirmed_at' => now(),
            'profile_completed_at' => now(),
        ]);
        $user->assignRole(RbacCatalog::ROLE_BENEFICIARY);
        $user->profile()->create([
            'gender' => ProfileGender::Female,
            'birth_date' => '1995-04-12',
            'city' => 'الرياض',
        ]);

        return $user->fresh(['profile']);
    }
}
