<?php

namespace Tests\Feature;

use App\Enums\IdentityType;
use App\Enums\ProfileGender;
use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use App\Support\Privacy\SensitiveContactMasker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiBeneficiariesTest extends TestCase
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
            'staff_ui.maintenance' => false,
            'staff_ui.ready_modules' => ['users'],
        ]);
    }

    public function test_guest_is_redirected_and_trainees_are_forbidden(): void
    {
        $this->get(route('staff-ui.users.index'))->assertRedirect(route('login'));

        $trainee = $this->beneficiary(['name' => 'مستفيد ممنوع']);

        $this->actingAsOtpVerified($trainee)
            ->get(route('staff-ui.users.index'))
            ->assertForbidden();
    }

    public function test_staff_without_otp_is_sent_to_verification(): void
    {
        $staff = $this->staff(['users.view']);

        $this->actingAs($staff)
            ->get(route('staff-ui.users.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_staff_without_list_permission_is_forbidden(): void
    {
        $staff = $this->staff();

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.users.index'))
            ->assertForbidden();

        $this->actingAsOtpVerified($staff)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('المستخدمين');
    }

    public function test_view_basic_opens_the_list_and_users_view_hides_identity_and_contact(): void
    {
        $basic = $this->staff(['beneficiaries.view_basic']);
        $listed = $this->completeBeneficiary();

        $this->actingAsOtpVerified($basic)
            ->get(route('staff-ui.users.index'))
            ->assertOk()
            ->assertSee($listed->fullName());

        $viewer = $this->staff([
            'users.view',
            'beneficiaries.identity.view_full',
        ]);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.users.index'))
            ->assertOk()
            ->assertSee('المستفيدين')
            ->assertSee('الموظفين')
            ->assertSee($listed->fullName())
            ->assertSee(SensitiveContactMasker::maskEmail($listed->email))
            ->assertSee(SensitiveContactMasker::maskPhone($listed->phone))
            ->assertDontSee($listed->email)
            ->assertDontSee($listed->phone)
            ->assertDontSee('9081')
            ->assertDontSee('1099889081')
            ->assertDontSee('تصدير المحدد');
    }

    public function test_contact_permission_shows_email_and_phone(): void
    {
        $staff = $this->staff(['users.view', 'beneficiaries.view_contact']);
        $listed = $this->completeBeneficiary();

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.users.index'))
            ->assertOk()
            ->assertSee($listed->email)
            ->assertSee($listed->phone)
            ->assertDontSee('9081');
    }

    public function test_search_and_filters_cover_status_and_profile_completeness(): void
    {
        $staff = $this->staff(['users.view']);
        $complete = $this->completeBeneficiary([
            'name' => 'نورة سعد محمد القحطاني',
            'email' => 'complete-beneficiary@example.com',
        ]);
        $incomplete = $this->beneficiary([
            'name' => 'زيد الناقص',
            'email' => 'incomplete-beneficiary@example.com',
            'phone' => '0555000222',
        ]);
        $inactive = $this->beneficiary([
            'name' => 'ليان المعطلة',
            'email' => 'inactive-beneficiary@example.com',
            'is_active' => false,
        ]);

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.users.index', ['q' => 'زيد']))
            ->assertOk()
            ->assertSee($incomplete->name)
            ->assertDontSee($complete->fullName())
            ->assertDontSee($inactive->name);

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.users.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee($inactive->name)
            ->assertDontSee($complete->fullName())
            ->assertDontSee($incomplete->name);

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.users.index', ['profile' => 'complete']))
            ->assertOk()
            ->assertSee($complete->fullName())
            ->assertSee('مكتمل')
            ->assertDontSee($incomplete->name)
            ->assertDontSee($inactive->name);

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.users.index', ['profile' => 'incomplete']))
            ->assertOk()
            ->assertSee($incomplete->name)
            ->assertSee($inactive->name)
            ->assertDontSee($complete->fullName());
    }

    public function test_staff_and_admin_accounts_stay_out_of_the_beneficiary_table(): void
    {
        $viewer = $this->staff(['users.view']);
        $employee = $this->staff([], ['name' => 'موظف لا يظهر', 'email' => 'hidden-staff@example.com']);
        $admin = $this->admin(['name' => 'أدمن لا يظهر', 'email' => 'hidden-admin@example.com']);
        $beneficiary = $this->beneficiary(['name' => 'مستفيد ظاهر', 'email' => 'visible-beneficiary@example.com']);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.users.index'))
            ->assertOk()
            ->assertSee($beneficiary->name)
            ->assertDontSee($employee->name)
            ->assertDontSee($employee->email)
            ->assertDontSee($admin->name)
            ->assertDontSee($admin->email);
    }

    public function test_sidebar_link_follows_the_current_list_permission(): void
    {
        $admin = $this->admin();

        $this->actingAsOtpVerified($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('المستخدمين')
            ->assertSee(route('staff-ui.users.index'), false);

        $this->actingAsOtpVerified($this->staff(['users.view', 'statistics.view']))
            ->get('/admin')
            ->assertOk()
            ->assertDontSee(route('staff-ui.users.index'), false)
            ->assertDontSee('لوحة التحكم الجديدة قيد البناء');
    }

    public function test_pagination_keeps_the_search(): void
    {
        $staff = $this->staff(['users.view']);

        foreach (range(1, 16) as $number) {
            $this->beneficiary([
                'name' => sprintf('مستفيد جدول %02d', $number),
                'email' => sprintf('paged-%02d@example.com', $number),
            ]);
        }

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.users.index', ['q' => 'جدول', 'page' => 2]))
            ->assertOk()
            ->assertSee('مستفيد جدول 16')
            ->assertDontSee('مستفيد جدول 01')
            ->assertSee('page=1', false);
    }

    public function test_maintenance_keeps_the_page_closed_for_staff(): void
    {
        config([
            'staff_ui.maintenance' => true,
            'staff_ui.ready_modules' => [],
        ]);

        $staff = $this->staff(['users.view']);
        $admin = $this->admin();
        $this->beneficiary(['name' => 'مستفيد أثناء الإغلاق']);

        $this->actingAsOtpVerified($staff)
            ->get(route('staff-ui.users.index'))
            ->assertForbidden();

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.users.index'))
            ->assertOk()
            ->assertSee('مستفيد أثناء الإغلاق')
            ->assertDontSee(self::MAINTENANCE_HEADING);

        $this->assertNotContains('users', config('staff_ui.ready_modules'));
    }

    /**
     * @param  list<string>  $permissions
     * @param  array<string, mixed>  $overrides
     */
    private function staff(array $permissions = [], array $overrides = []): User
    {
        $staff = User::factory()->create(array_merge([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $overrides));
        $staff->assignRole(RbacCatalog::ROLE_STAFF);
        if ($permissions !== []) {
            $staff->givePermissionTo($permissions);
        }

        return $staff->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function admin(array $overrides = []): User
    {
        $admin = User::factory()->create(array_merge([
            'role_type' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $overrides));
        $admin->assignRole(RbacCatalog::ROLE_ADMIN);

        return $admin->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function beneficiary(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $overrides));
        $user->assignRole(RbacCatalog::ROLE_BENEFICIARY);

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function completeBeneficiary(array $overrides = []): User
    {
        $user = $this->beneficiary(array_merge([
            'name' => 'نورة سعد محمد القحطاني',
            'first_name' => 'نورة',
            'father_name' => 'سعد',
            'grandfather_name' => 'محمد',
            'family_name' => 'القحطاني',
            'email' => 'nora.complete@example.com',
            'phone' => '0555123490',
        ], $overrides));

        $user->forceFill([
            'identity_type' => IdentityType::NationalId,
            'identity_number_last4' => '9081',
            'identity_number_lookup_hash' => hash('sha256', 'staff-ui-beneficiary-'.$user->id),
        ])->save();

        $user->profile()->create([
            'gender' => ProfileGender::Female,
            'birth_date' => '1995-04-12',
            'city' => 'الرياض',
        ]);

        return $user->fresh(['profile']);
    }
}
