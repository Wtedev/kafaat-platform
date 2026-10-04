<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiDemoTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbacRoles();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('staff-ui.demo'))->assertRedirect(route('login'));
    }

    public function test_staff_cannot_open_the_demo(): void
    {
        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
        ]);
        $staff->assignRole(RbacCatalog::ROLE_STAFF);

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.demo'))
            ->assertForbidden();
    }

    public function test_super_admin_sees_the_arabic_demo(): void
    {
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'name' => 'مدير المعاينة',
        ]);
        $admin->assignRole(RbacCatalog::ROLE_ADMIN);

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.demo'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('كفاءات')
            ->assertSee('لوحة التحكم')
            ->assertDontSee('data-sui-global-search', false)
            ->assertDontSee('aria-label="مساعدة"', false)
            ->assertDontSee('aria-label="الإعدادات"', false)
            ->assertSee('آخر 30 يوم')
            ->assertSee('IBM+Plex+Sans+Arabic', false)
            ->assertSee(Vite::asset('resources/css/staff-ui.css'), false)
            ->assertSee(Vite::asset('resources/js/staff-ui.js'), false)
            ->assertSee('js/chart.umd.min.js', false);
    }

    public function test_topbar_tools_return_when_config_enables_them(): void
    {
        config([
            'staff_ui.topbar.search' => true,
            'staff_ui.topbar.help' => true,
            'staff_ui.topbar.settings' => true,
        ]);

        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
        ]);
        $admin->assignRole(RbacCatalog::ROLE_ADMIN);

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.demo'))
            ->assertOk()
            ->assertSee('data-sui-global-search', false)
            ->assertSee('aria-label="مساعدة"', false)
            ->assertSee('aria-label="الإعدادات"', false);
    }
}
