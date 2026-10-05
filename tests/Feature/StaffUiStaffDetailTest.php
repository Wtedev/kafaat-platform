<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\StaffEmailChangedByAdminNotification;
use App\Services\Rbac\RbacCatalog;
use App\Services\StaffUi\StaffInvitationService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiStaffDetailTest extends TestCase
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
            'staff_ui.invites_enabled' => true,
        ]);
    }

    public function test_staff_row_opens_the_detail_page_and_preview_mode_still_forbids_non_admins(): void
    {
        $viewer = $this->staff(['users.view']);
        $member = $this->namedStaff();

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.users.staff.index'))
            ->assertOk()
            ->assertSee(route('staff-ui.users.staff.show', $member), false);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.users.staff.show', $member))
            ->assertOk()
            ->assertSee('بيانات الموظف')
            ->assertSee('ليان سعد علي العمر')
            ->assertSee('layan@example.com')
            ->assertSee('0550000000')
            ->assertSee('موظف')
            ->assertSee('نشط')
            ->assertSee('2026-09-01')
            ->assertSee('تعديل الصلاحيات من قسم الصلاحيات')
            ->assertDontSee('تعديل البيانات');

        $this->actingAsOtpVerified($this->staff([]))
            ->get(route('staff-ui.users.staff.show', $member))
            ->assertForbidden();

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.users.staff.show', $this->beneficiary()))
            ->assertNotFound();

        config(['staff_ui.ready_modules' => []]);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.users.staff.show', $member))
            ->assertForbidden();

        $this->actingAsOtpVerified($this->admin())
            ->get(route('staff-ui.users.staff.show', $member))
            ->assertOk()
            ->assertSee('بيانات الموظف');
    }

    public function test_effective_permissions_mark_role_grants_and_direct_grants(): void
    {
        Role::findByName(RbacCatalog::ROLE_STAFF)->givePermissionTo('programs.view');
        $member = $this->namedStaff();
        $member->givePermissionTo('users.update');

        $response = $this->actingAsOtpVerified($this->admin())
            ->get(route('staff-ui.users.staff.show', $member))
            ->assertOk()
            ->assertSee('البرامج التدريبية')
            ->assertSee('المستخدمون')
            ->assertSee('يشوف')
            ->assertSee('يعدل')
            ->assertSee('من الدور')
            ->assertSee('مباشرة');

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/عرض البرامج.*?يشوف.*?من الدور/s', $html);
        $this->assertMatchesRegularExpression('/<li>\s*<span>تعديل المستخدمين<\/span>(?:(?!<\/li>).)*مباشرة/s', $html);
        $this->assertDoesNotMatchRegularExpression('/<li>\s*<span>تعديل المستخدمين<\/span>(?:(?!<\/li>).)*من الدور/s', $html);
    }

    public function test_editing_name_and_email_logs_out_notifies_both_addresses_and_audits_field_names(): void
    {
        Notification::fake();
        $editor = $this->staff(['users.view', 'users.update']);
        $member = $this->namedStaff();
        DB::table('sessions')->insert([
            'id' => 'staff-session-1',
            'user_id' => $member->id,
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        $this->actingAsOtpVerified($editor)
            ->post(route('staff-ui.users.staff.update', $member), [
                'first_name' => 'هند',
                'father_name' => 'سعد',
                'grandfather_name' => 'علي',
                'family_name' => 'العمر',
                'email' => 'layan.new@example.com',
            ])
            ->assertRedirect(route('staff-ui.users.staff.show', $member));

        $member->refresh();
        $this->assertSame('هند سعد علي العمر', $member->name);
        $this->assertSame('layan.new@example.com', $member->email);
        $this->assertNull($member->email_verified_at);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $member->id)->count());

        Notification::assertSentOnDemandTimes(StaffEmailChangedByAdminNotification::class, 2);
        foreach (['layan@example.com', 'layan.new@example.com'] as $address) {
            Notification::assertSentOnDemand(
                StaffEmailChangedByAdminNotification::class,
                function (StaffEmailChangedByAdminNotification $notification, array $channels, object $notifiable) use ($address): bool {
                    return ($notifiable->routes['mail'] ?? null) === $address
                        && str_contains($notification->toMail($notifiable)->render(), 'قام أحد المشرفين بتغيير البريد الإلكتروني');
                },
            );
        }

        $audit = AuditLog::query()->where('action', 'staff.updated')->where('target_user_id', $member->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame($editor->id, $audit->actor_id);
        $this->assertContains('email', $audit->metadata['fields']);
        $this->assertContains('first_name', $audit->metadata['fields']);
        $encoded = (string) json_encode($audit->metadata, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('layan.new@example.com', $encoded);
        $this->assertStringNotContainsString('layan@example.com', $encoded);
    }

    public function test_email_must_be_unique_and_an_admin_cannot_change_their_own_email_here(): void
    {
        Notification::fake();
        $editor = $this->admin([
            'first_name' => 'لمى',
            'father_name' => 'علي',
            'grandfather_name' => 'سعد',
            'family_name' => 'القحطاني',
            'email' => 'admin@example.com',
        ]);
        $member = $this->namedStaff();
        $this->namedStaff(['email' => 'taken@example.com', 'name' => 'ماجد فهد ناصر السالم']);

        $this->actingAsOtpVerified($editor)
            ->from(route('staff-ui.users.staff.show', $member))
            ->post(route('staff-ui.users.staff.update', $member), [
                'first_name' => 'ليان',
                'father_name' => 'سعد',
                'grandfather_name' => 'علي',
                'family_name' => 'العمر',
                'email' => 'taken@example.com',
            ])
            ->assertRedirect(route('staff-ui.users.staff.show', $member))
            ->assertSessionHasErrors('email');
        $this->assertSame('layan@example.com', $member->fresh()->email);

        $this->actingAsOtpVerified($editor)
            ->get(route('staff-ui.users.staff.show', $editor))
            ->assertOk()
            ->assertSee(route('staff-ui.profile'), false)
            ->assertSee('غيّره من ملفك الشخصي')
            ->assertDontSee('name="email"', false);

        $this->actingAsOtpVerified($editor)
            ->post(route('staff-ui.users.staff.update', $editor), [
                'first_name' => 'لمى',
                'father_name' => 'علي',
                'grandfather_name' => 'سعد',
                'family_name' => 'القحطاني',
                'email' => 'other-admin@example.com',
            ])
            ->assertForbidden();
        $this->assertSame('admin@example.com', $editor->fresh()->email);
        Notification::assertNothingSent();
    }

    public function test_password_reset_link_is_rate_limited_audited_and_blocked_for_invited_or_inactive_staff(): void
    {
        Notification::fake();
        $editor = $this->staff(['users.view', 'users.update', 'users.create']);
        $member = $this->namedStaff();

        $this->actingAsOtpVerified($editor)
            ->post(route('staff-ui.users.staff.password-reset', $member))
            ->assertRedirect(route('staff-ui.users.staff.show', $member));

        Notification::assertSentTo($member, ResetPassword::class);
        $this->assertSame(1, AuditLog::query()->where('action', 'staff.password_reset_sent')->where('target_user_id', $member->id)->count());
        $audit = AuditLog::query()->where('action', 'staff.password_reset_sent')->first();
        $this->assertSame($editor->id, $audit->actor_id);
        $this->assertStringNotContainsString('layan@example.com', (string) json_encode($audit->getAttributes(), JSON_UNESCAPED_UNICODE));

        $this->actingAsOtpVerified($editor)
            ->from(route('staff-ui.users.staff.show', $member))
            ->post(route('staff-ui.users.staff.password-reset', $member))
            ->assertRedirect(route('staff-ui.users.staff.show', $member))
            ->assertSessionHasErrors('password_reset');
        $this->assertSame(1, AuditLog::query()->where('action', 'staff.password_reset_sent')->count());

        $inactive = $this->namedStaff([
            'email' => 'inactive.staff@example.com',
            'name' => 'ماجد فهد ناصر السالم',
            'is_active' => false,
        ]);
        $this->actingAsOtpVerified($editor)
            ->get(route('staff-ui.users.staff.show', $inactive))
            ->assertOk()
            ->assertSee('لا يمكن إرسال رابط لحساب معطّل.');
        $this->actingAsOtpVerified($editor)
            ->post(route('staff-ui.users.staff.password-reset', $inactive))
            ->assertStatus(422);

        $invited = app(StaffInvitationService::class)->invite('هدى علي سعد العمر', 'huda.staff@example.com', RbacCatalog::ROLE_STAFF);
        $this->actingAsOtpVerified($editor)
            ->get(route('staff-ui.users.staff.show', $invited))
            ->assertOk()
            ->assertSee('مدعو')
            ->assertSee('بانتظار تعيين كلمة المرور')
            ->assertSee('إعادة إرسال الدعوة')
            ->assertDontSee('إرسال رابط إعادة تعيين كلمة المرور');
        $this->actingAsOtpVerified($editor)
            ->post(route('staff-ui.users.staff.password-reset', $invited))
            ->assertStatus(422);
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
    private function namedStaff(array $overrides = []): User
    {
        return $this->staff([], array_merge([
            'name' => 'ليان سعد علي العمر',
            'first_name' => 'ليان',
            'father_name' => 'سعد',
            'grandfather_name' => 'علي',
            'family_name' => 'العمر',
            'email' => 'layan@example.com',
            'phone' => '0550000000',
            'last_login_at' => '2026-09-01 10:30:00',
        ], $overrides));
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
}
