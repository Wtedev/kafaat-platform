<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiMaintenanceTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    private const MAINTENANCE_HEADING = 'واجهة الموظفين قيد العمل حالياً';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        config([
            'staff_ui.maintenance' => true,
            'staff_ui.ready_modules' => [],
        ]);
    }

    public function test_staff_see_the_maintenance_page_on_staff_urls(): void
    {
        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
        ]);
        $staff->assignRole(RbacCatalog::ROLE_STAFF);

        foreach (['/admin', '/admin/users'] as $url) {
            $this->actingAsOtpVerified($staff)
                ->get($url)
                ->assertServiceUnavailable()
                ->assertSee(self::MAINTENANCE_HEADING)
                ->assertSee('نعمل على تطوير الواجهة وسنعود قريباً')
                ->assertSee('تسجيل الخروج')
                ->assertSee(Vite::asset('resources/css/staff-ui.css'), false)
                ->assertSee('lang="ar-SA-u-nu-latn"', false)
                ->assertDontSee('IBM+Plex+Sans+Arabic', false)
                ->assertDontSee('fonts.googleapis.com', false)
                ->assertSee('dir="rtl"', false)
                ->assertDontSee('البرنامج الحالي');
        }

        foreach (['/staff-ui/demo', '/staff-ui/profile', '/staff-ui/users'] as $url) {
            $this->actingAsOtpVerified($staff)
                ->get($url)
                ->assertForbidden();
        }
    }

    public function test_super_admin_lands_on_the_new_dashboard(): void
    {
        $admin = User::factory()->create([
            'role_type' => 'admin',
            'is_active' => true,
            'name' => 'مدير النظام',
        ]);
        $admin->assignRole(RbacCatalog::ROLE_ADMIN);

        $this->actingAsOtpVerified($admin)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee(self::MAINTENANCE_HEADING)
            ->assertSee('dir="rtl"', false)
            ->assertSee(Vite::asset('resources/css/staff-ui.css'), false)
            ->assertSee('لوحة التحكم الجديدة قيد البناء')
            ->assertSee('سيتم إضافة الأقسام تدريجياً')
            ->assertDontSee('aria-disabled="true"', false)
            ->assertDontSee('البرنامج الحالي')
            ->assertSee('البرامج')
            ->assertSee(route('staff-ui.programs.index'), false)
            ->assertSee('تسجيل الخروج')
            ->assertSee(route('logout'), false)
            ->assertSee(route('staff-ui.profile'), false)
            ->assertDontSee('data-sui-global-search', false)
            ->assertDontSee('aria-label="مساعدة"', false)
            ->assertDontSee('aria-label="الإعدادات"', false);
    }

    public function test_ready_module_stays_open_for_staff(): void
    {
        config(['staff_ui.ready_modules' => ['users']]);

        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
        ]);
        $staff->assignRole(RbacCatalog::ROLE_STAFF);
        $staff->givePermissionTo('users.view');

        $this->actingAsOtpVerified($staff)
            ->get('/admin/users')
            ->assertOk()
            ->assertDontSee(self::MAINTENANCE_HEADING);

        $this->actingAsOtpVerified($staff)
            ->get('/admin')
            ->assertServiceUnavailable()
            ->assertSee(self::MAINTENANCE_HEADING);
    }

    public function test_public_and_trainee_routes_stay_open(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee(self::MAINTENANCE_HEADING);

        $trainee = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $trainee->assignRole(RbacCatalog::ROLE_BENEFICIARY);

        $portal = $this->actingAsOtpVerified($trainee)->get(route('portal.dashboard'));

        $this->assertNotSame(503, $portal->status());
        $portal->assertDontSee(self::MAINTENANCE_HEADING);
    }
}
