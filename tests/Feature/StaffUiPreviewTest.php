<?php

namespace Tests\Feature;

use App\Filament\Widgets\PlatformStatsWidget;
use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiPreviewTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
        config([
            'staff_ui.ready_modules' => [],
        ]);
    }

    public function test_staff_keep_the_filament_dashboard_and_cannot_open_the_new_ui_when_maintenance_is_off(): void
    {
        config(['staff_ui.maintenance' => false]);
        $staff = $this->staff(['users.view', 'statistics.view']);

        $this->actingAsOtpVerified($staff)
            ->get('/admin')
            ->assertOk()
            ->assertSee('PlatformStatsWidget', false)
            ->assertSee('fi-sidebar', false)
            ->assertDontSee('لوحة التحكم الجديدة قيد البناء')
            ->assertDontSee(route('staff-ui.profile'), false);

        Livewire::actingAs($staff)
            ->test(PlatformStatsWidget::class)
            ->assertSee('إجمالي المستخدمين');

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.users.index'))
            ->assertForbidden();

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.profile'))
            ->assertForbidden();
    }

    public function test_admin_sees_the_new_dashboard_when_maintenance_is_off(): void
    {
        config(['staff_ui.maintenance' => false]);

        $this->actingAsOtpVerified($this->admin())
            ->get('/admin')
            ->assertOk()
            ->assertSee('لوحة التحكم الجديدة قيد البناء')
            ->assertDontSee('إجمالي المستخدمين');
    }

    public function test_maintenance_on_still_blocks_staff_filament_and_forbids_the_new_ui(): void
    {
        config(['staff_ui.maintenance' => true]);
        $staff = $this->staff(['users.view', 'statistics.view']);

        $this->actingAsOtpVerified($staff)
            ->get('/admin')
            ->assertServiceUnavailable()
            ->assertSee('واجهة الموظفين قيد العمل حالياً')
            ->assertDontSee('إجمالي المستخدمين')
            ->assertDontSee('لوحة التحكم الجديدة قيد البناء');

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.users.index'))
            ->assertForbidden();

        $this->actingAsOtpVerified($this->admin())
            ->get('/admin')
            ->assertOk()
            ->assertSee('لوحة التحكم الجديدة قيد البناء');
    }

    public function test_a_ready_module_lets_staff_open_that_new_page_only(): void
    {
        config([
            'staff_ui.maintenance' => false,
            'staff_ui.ready_modules' => ['users'],
        ]);
        $staff = $this->staff(['users.view']);

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.users.index'))
            ->assertOk();

        $this->actingAsOtpVerified($staff)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('لوحة التحكم الجديدة قيد البناء');
    }

    /**
     * @param  list<string>  $permissions
     */
    private function staff(array $permissions): User
    {
        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole(RbacCatalog::ROLE_STAFF);
        $staff->givePermissionTo($permissions);

        return $staff->fresh();
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
}
