<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('ابحث...')
            ->assertSee('آخر 30 يوم')
            ->assertSee('IBM+Plex+Sans+Arabic', false)
            ->assertSee('staff-ui.css', false);
    }
}
