<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesSeederLeavesUsersUntouchedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_roles_does_not_demote_an_extra_admin_or_rewrite_staff_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['app.admin_email' => 'primary@example.test']);

        $extra = User::factory()->create([
            'email' => 'extra@example.test',
            'role_type' => RbacCatalog::ROLE_ADMIN,
        ]);
        $extra->syncRoles([RbacCatalog::ROLE_ADMIN]);

        $staff = User::factory()->create([
            'email' => 'staff@example.test',
            'role_type' => RbacCatalog::ROLE_STAFF,
        ]);
        $staff->syncRoles([RbacCatalog::ROLE_STAFF]);
        $staff->givePermissionTo('programs.view');

        $this->seed(RolesAndPermissionsSeeder::class);

        $extra->refresh();
        $staff->refresh();

        $this->assertSame(RbacCatalog::ROLE_ADMIN, $extra->role_type);
        $this->assertTrue($extra->hasRole(RbacCatalog::ROLE_ADMIN));
        $this->assertTrue($staff->hasPermissionTo('programs.view'));
        $this->assertSame(RbacCatalog::ROLE_STAFF, $staff->role_type);
    }
}
