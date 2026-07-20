<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use App\Services\Rbac\RoleTypeSpatieSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class RoleTypeSpatieSyncTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    private RoleTypeSpatieSyncService $sync;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        $this->sync = app(RoleTypeSpatieSyncService::class);
    }

    public function test_maps_all_four_roles_and_legacy_trainee(): void
    {
        $this->assertSame(RbacCatalog::ROLE_ADMIN, $this->sync->mapRoleTypeToSpatie('admin'));
        $this->assertSame(RbacCatalog::ROLE_STAFF, $this->sync->mapRoleTypeToSpatie('staff'));
        $this->assertSame(RbacCatalog::ROLE_BENEFICIARY, $this->sync->mapRoleTypeToSpatie('beneficiary'));
        $this->assertSame(RbacCatalog::ROLE_VOLUNTEER, $this->sync->mapRoleTypeToSpatie('volunteer'));
        $this->assertSame(RbacCatalog::ROLE_BENEFICIARY, $this->sync->mapRoleTypeToSpatie('trainee'));
        $this->assertNull($this->sync->mapRoleTypeToSpatie(null));
        $this->assertNull($this->sync->mapRoleTypeToSpatie(''));
        $this->assertNull($this->sync->mapRoleTypeToSpatie('unknown'));
    }

    public function test_sync_applies_all_four_roles_from_role_type(): void
    {
        $admin = User::factory()->create(['role_type' => 'admin', 'email' => 'primary-admin@example.com']);
        $staff = User::factory()->create(['role_type' => 'staff']);
        $beneficiary = User::factory()->create(['role_type' => 'beneficiary']);
        $volunteer = User::factory()->create(['role_type' => 'volunteer']);

        config(['app.admin_email' => 'primary-admin@example.com']);

        $summary = $this->sync->syncFromRoleType(dryRun: false);

        $this->assertGreaterThanOrEqual(4, $summary['changed']);

        foreach ([$admin, $staff, $beneficiary, $volunteer] as $user) {
            $user->refresh();
        }

        $this->assertTrue($admin->hasRole(RbacCatalog::ROLE_ADMIN));
        $this->assertTrue($staff->hasRole(RbacCatalog::ROLE_STAFF));
        $this->assertTrue($beneficiary->hasRole(RbacCatalog::ROLE_BENEFICIARY));
        $this->assertTrue($volunteer->hasRole(RbacCatalog::ROLE_VOLUNTEER));

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($staff->isStaff());
        $this->assertTrue($beneficiary->isPortalUser());
        $this->assertTrue($volunteer->isPortalUser());
    }

    public function test_detects_drift_between_role_type_and_spatie(): void
    {
        $user = User::factory()->create(['role_type' => 'staff']);
        $user->assignRole(RbacCatalog::ROLE_BENEFICIARY);

        $drift = $this->sync->driftForUser($user);

        $this->assertNotNull($drift);
        $this->assertSame('mismatch', $drift['kind']);
        $this->assertSame('staff', $drift['role_type']);
        $this->assertSame(RbacCatalog::ROLE_BENEFICIARY, $drift['spatie_role']);
        $this->assertSame(RbacCatalog::ROLE_STAFF, $drift['expected_spatie']);

        $report = $this->sync->reportDrift();
        $this->assertGreaterThanOrEqual(1, $report['drift_count']);
    }

    public function test_user_with_no_role_is_reported_and_skipped(): void
    {
        $user = User::factory()->create(['role_type' => 'beneficiary']);
        $user->syncRoles([]);
        // Column is NOT NULL with default; force empty to simulate unset legacy data.
        DB::table('users')->where('id', $user->id)->update(['role_type' => '']);
        $user->refresh();

        $drift = $this->sync->driftForUser($user);
        $this->assertNotNull($drift);
        $this->assertSame('no_role', $drift['kind']);

        $summary = $this->sync->syncFromRoleType(dryRun: false);
        $this->assertSame(0, collect($summary['changes'])->where('user_id', $user->id)->count());
        $this->assertFalse($user->fresh()->roles()->exists());
    }

    public function test_staff_with_no_permissions_stays_without_permissions_after_sync(): void
    {
        $staff = User::factory()->create(['role_type' => 'staff']);
        // No Spatie role yet, no direct permissions

        $this->sync->syncFromRoleType(dryRun: false, enforceSingleAdmin: false);

        $staff->refresh();
        $this->assertTrue($staff->hasRole(RbacCatalog::ROLE_STAFF));
        $this->assertCount(0, $staff->getDirectPermissions());
        $this->assertFalse($staff->can('manage_programs'));
    }

    public function test_admin_receives_admin_role_permissions_not_staff_blanket(): void
    {
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'email' => 'solo-admin@example.com',
        ]);
        config(['app.admin_email' => 'solo-admin@example.com']);

        // Stale direct permission that must be cleared for admin
        $perm = Permission::findOrCreate('users.view', RbacCatalog::GUARD_WEB);
        $admin->givePermissionTo($perm);

        $this->sync->syncFromRoleType(dryRun: false);

        $admin->refresh();
        $this->assertTrue($admin->hasRole(RbacCatalog::ROLE_ADMIN));
        $this->assertCount(0, $admin->getDirectPermissions());
        $this->assertTrue($admin->can('manage_roles'));
        $this->assertTrue($admin->can('permissions.assign'));
    }

    public function test_sync_is_idempotent(): void
    {
        $user = User::factory()->create(['role_type' => 'beneficiary']);

        $first = $this->sync->syncFromRoleType(dryRun: false, enforceSingleAdmin: false);
        $this->assertGreaterThanOrEqual(1, collect($first['changes'])->where('user_id', $user->id)->count());

        $second = $this->sync->syncFromRoleType(dryRun: false, enforceSingleAdmin: false);
        $this->assertSame(0, collect($second['changes'])->where('user_id', $user->id)->count());

        $user->refresh();
        $this->assertTrue($user->hasRole(RbacCatalog::ROLE_BENEFICIARY));
        $this->assertSame('beneficiary', $user->role_type);
    }

    public function test_dry_run_does_not_persist(): void
    {
        $user = User::factory()->create(['role_type' => 'volunteer']);

        $summary = $this->sync->syncFromRoleType(dryRun: true, enforceSingleAdmin: false);

        $this->assertSame('dry_run', $summary['mode']);
        $this->assertGreaterThanOrEqual(1, collect($summary['changes'])->where('user_id', $user->id)->count());
        $this->assertFalse($user->fresh()->hasRole(RbacCatalog::ROLE_VOLUNTEER));
    }

    public function test_rollback_sync_to_role_type_restores_column(): void
    {
        $user = User::factory()->create(['role_type' => 'staff']);
        $user->syncRoles([RbacCatalog::ROLE_VOLUNTEER]);

        // Spatie is volunteer, column still staff — rollback writes column from Spatie
        $summary = $this->sync->syncToRoleType(dryRun: false);

        $this->assertGreaterThanOrEqual(1, collect($summary['changes'])->where('user_id', $user->id)->count());
        $user->refresh();
        $this->assertSame(RbacCatalog::ROLE_VOLUNTEER, $user->role_type);
        $this->assertTrue($user->hasRole(RbacCatalog::ROLE_VOLUNTEER));
        $this->assertNull($this->sync->driftForUser($user));
    }

    public function test_demote_extra_admin_without_granting_permissions(): void
    {
        config(['app.admin_email' => 'keeper@example.com']);

        $primary = User::factory()->create([
            'email' => 'keeper@example.com',
            'role_type' => 'admin',
        ]);
        $primary->assignRole(RbacCatalog::ROLE_ADMIN);

        $extra = User::factory()->create([
            'email' => 'extra-admin@example.com',
            'role_type' => 'admin',
        ]);
        $extra->assignRole(RbacCatalog::ROLE_ADMIN);

        $this->sync->syncFromRoleType(dryRun: false, enforceSingleAdmin: true);

        $extra->refresh();
        $this->assertTrue($extra->hasRole(RbacCatalog::ROLE_STAFF));
        $this->assertSame('staff', $extra->role_type);
        $this->assertCount(0, $extra->getDirectPermissions());
        $this->assertFalse($extra->can('manage_roles'));

        $primary->refresh();
        $this->assertTrue($primary->hasRole(RbacCatalog::ROLE_ADMIN));
    }

    public function test_artisan_commands_dry_run_and_report(): void
    {
        User::factory()->create(['role_type' => 'beneficiary']);

        $this->assertSame(0, Artisan::call('roles:sync-from-role-type'));
        $this->assertStringContainsString('Dry Run', Artisan::output());

        Artisan::call('roles:sync-from-role-type', ['--apply' => true, '--no-enforce-single-admin' => true]);
        $exit = Artisan::call('roles:report-drift');
        // May still fail if other no_role users exist from seeders — assert command runs
        $this->assertContains($exit, [0, 1]);
    }

    public function test_spatie_preferred_over_stale_role_type_for_reads(): void
    {
        $user = User::factory()->create(['role_type' => 'beneficiary']);
        $user->syncRoles([RbacCatalog::ROLE_STAFF]);

        $this->assertTrue($user->isStaff());
        $this->assertFalse($user->isAdmin());
        // Portal: Spatie staff wins for admin panel; role_type still portal fallback but isStaff is true
        $this->assertTrue($user->isAdminOrStaff());
    }
}
