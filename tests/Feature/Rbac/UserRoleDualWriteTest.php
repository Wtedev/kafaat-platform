<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use App\Services\Auth\UserRegistrationService;
use App\Services\Rbac\RbacCatalog;
use App\Services\Rbac\RoleTypeSpatieSyncService;
use Database\Seeders\RetentionPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\Concerns\SeedsActivePrivacyPolicy;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class UserRoleDualWriteTest extends TestCase
{
    use GeneratesTestIdentityData;
    use RefreshDatabase;
    use SeedsActivePrivacyPolicy;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        $this->seedActivePrivacyPolicy();
        $this->seed(RetentionPolicySeeder::class);
    }

    public function test_registration_dual_writes_beneficiary_role_type_and_spatie_role(): void
    {
        $identity = $this->generateValidNationalId();
        $policy = $this->seedActivePrivacyPolicy();

        $user = app(UserRegistrationService::class)->register([
            'email' => 'dual-write@example.com',
            'password' => 'SecretPass1!',
            'first_name' => 'أحمد',
            'father_name' => 'محمد',
            'grandfather_name' => 'عبدالله',
            'family_name' => 'العتيبي',
            'identity_type' => 'national_id',
            'identity_number' => $identity,
            'phone' => '0501234567',
            'birth_date' => '2000-01-01',
            'gender' => 'female',
        ], $policy);

        $user->refresh();

        $this->assertSame('beneficiary', $user->role_type);
        $this->assertTrue($user->hasRole(RbacCatalog::ROLE_BENEFICIARY));
        $this->assertTrue($user->isPortalUser());
        $this->assertFalse($user->isAdminOrStaff());
    }

    public function test_legacy_role_type_only_accounts_still_resolve_via_helpers(): void
    {
        $admin = User::factory()->create(['role_type' => 'admin']);
        $staff = User::factory()->create(['role_type' => 'staff']);
        $beneficiary = User::factory()->create(['role_type' => 'beneficiary']);
        $volunteer = User::factory()->create(['role_type' => 'volunteer']);
        $trainee = User::factory()->create(['role_type' => 'trainee']);

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($staff->isStaff());
        $this->assertTrue($beneficiary->isPortalUser());
        $this->assertTrue($volunteer->isPortalUser());
        $this->assertTrue($trainee->isPortalUser());
    }

    public function test_spatie_only_accounts_still_resolve_via_helpers(): void
    {
        $admin = User::factory()->create(['role_type' => 'beneficiary']);
        $admin->syncRoles([RbacCatalog::ROLE_ADMIN]);

        $staff = User::factory()->create(['role_type' => 'beneficiary']);
        $staff->syncRoles([RbacCatalog::ROLE_STAFF]);

        $beneficiary = User::factory()->create(['role_type' => 'volunteer']);
        $beneficiary->syncRoles([RbacCatalog::ROLE_BENEFICIARY]);

        $volunteer = User::factory()->create(['role_type' => 'beneficiary']);
        $volunteer->syncRoles([RbacCatalog::ROLE_VOLUNTEER]);

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($staff->isStaff());
        $this->assertTrue($beneficiary->isPortalUser());
        $this->assertFalse($beneficiary->hasRole(RbacCatalog::ROLE_VOLUNTEER));
        $this->assertTrue($volunteer->isPortalUser());
        $this->assertTrue($volunteer->hasRole(RbacCatalog::ROLE_VOLUNTEER));
    }

    public function test_sync_from_role_type_repairs_spatie_without_changing_staff_permissions(): void
    {
        $staff = User::factory()->create(['role_type' => 'staff']);
        $staff->assignRole(RbacCatalog::ROLE_BENEFICIARY);
        $staff->givePermissionTo('manage_programs');

        app(RoleTypeSpatieSyncService::class)->syncFromRoleType(dryRun: false);

        $staff->refresh();

        $this->assertTrue($staff->hasRole(RbacCatalog::ROLE_STAFF));
        $this->assertTrue($staff->can('manage_programs'));
    }

    public function test_spatie_role_takes_precedence_over_conflicting_role_type_for_access_helpers(): void
    {
        $user = User::factory()->create(['role_type' => 'beneficiary']);
        $user->syncRoles([RbacCatalog::ROLE_STAFF]);

        $this->assertTrue($user->isStaff());
        $this->assertTrue($user->isAdminOrStaff());
        $this->assertFalse($user->isAdmin());
    }

    public function test_role_type_only_staff_without_spatie_is_repaired_by_sync_command(): void
    {
        $staff = User::factory()->create(['role_type' => 'staff']);
        $staff->syncRoles([]);

        $this->assertFalse($staff->hasRole(RbacCatalog::ROLE_STAFF));

        $this->artisan('roles:sync-from-role-type', ['--apply' => true, '--no-enforce-single-admin' => true])
            ->assertSuccessful();

        $staff->refresh();
        $this->assertTrue($staff->hasRole(RbacCatalog::ROLE_STAFF));
        $this->assertTrue($staff->isStaff());
    }

    public function test_direct_permission_survives_role_type_spatie_sync(): void
    {
        $staff = User::factory()->create(['role_type' => 'staff']);
        $staff->assignRole(RbacCatalog::ROLE_BENEFICIARY);
        $staff->givePermissionTo('manage_programs');

        app(RoleTypeSpatieSyncService::class)->syncFromRoleType(dryRun: false);

        $staff->refresh();

        $this->assertTrue($staff->hasRole(RbacCatalog::ROLE_STAFF));
        $this->assertTrue($staff->hasDirectPermission('manage_programs'));
        $this->assertTrue($staff->can('manage_programs'));
    }

    public function test_sync_to_role_type_aligns_column_when_spatie_is_source(): void
    {
        $user = User::factory()->create(['role_type' => 'beneficiary']);
        $user->syncRoles([RbacCatalog::ROLE_VOLUNTEER]);

        app(RoleTypeSpatieSyncService::class)->syncToRoleType(dryRun: false);

        $user->refresh();

        $this->assertSame(RbacCatalog::ROLE_VOLUNTEER, $user->role_type);
        $this->assertTrue($user->hasRole(RbacCatalog::ROLE_VOLUNTEER));
    }
}
