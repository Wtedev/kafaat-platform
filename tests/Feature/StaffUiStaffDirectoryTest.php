<?php

namespace Tests\Feature;

use App\Filament\Support\UserInlineEditSupport;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Rbac\PermissionMatrixCatalog;
use App\Notifications\StaffInvitationNotification;
use App\Services\Rbac\RbacCatalog;
use App\Services\StaffUi\StaffInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiStaffDirectoryTest extends TestCase
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

    public function test_users_view_opens_the_staff_tab_beside_beneficiaries(): void
    {
        $viewer = $this->staff(['users.view']);
        $member = $this->staff([], ['name' => 'ليان سعد', 'email' => 'layan@example.com', 'last_login_at' => '2026-09-01 10:30:00']);
        $this->staff([], ['name' => 'ماجد بعيد', 'email' => 'majed.other@example.com']);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.users.index'))
            ->assertOk()
            ->assertSee(route('staff-ui.users.staff.index'), false);

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.users.staff.index'))
            ->assertOk()
            ->assertSee('الموظفين')
            ->assertSee('المستفيدين')
            ->assertSee('الموظف')
            ->assertSee('ليان سعد')
            ->assertSee('layan@example.com')
            ->assertSee('موظف')
            ->assertSee('نشط')
            ->assertSee('2026-09-01')
            ->assertSee('أنت')
            ->assertSee('لم يسجل دخول بعد')
            ->assertSee('3 موظف')
            ->assertDontSee('تطبيق');

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.users.staff.index', ['q' => 'ليان', 'role' => 'staff', 'status' => 'active']))
            ->assertOk()
            ->assertSee('ليان سعد')
            ->assertSee('مسح الفلاتر')
            ->assertSee('1 موظف')
            ->assertDontSee('ماجد بعيد');

        $this->actingAsOtpVerified($viewer)
            ->get(route('staff-ui.users.staff.index', ['q' => 'missing-person']))
            ->assertOk()
            ->assertSee('لا توجد نتائج مطابقة.')
            ->assertSee('مسح الفلاتر')
            ->assertDontSee('ليان سعد');

        $this->assertNotNull($member->id);
    }

    public function test_invite_sends_an_arabic_set_password_link_and_accepting_it_activates_the_account(): void
    {
        Notification::fake();
        $admin = $this->admin();

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.users.staff.store'), [
                'name' => 'هدى العمر',
                'email' => 'huda.staff@example.com',
                'role' => RbacCatalog::ROLE_STAFF,
            ])
            ->assertRedirect(route('staff-ui.users.staff.index'));

        $invited = User::query()->where('email', 'huda.staff@example.com')->firstOrFail();
        $this->assertFalse($invited->is_active);
        $this->assertTrue($invited->hasRole(RbacCatalog::ROLE_STAFF));
        $this->assertTrue(app(StaffInvitationService::class)->isPending($invited));
        $this->assertNotSame('password', $invited->password);

        $token = null;
        Notification::assertSentTo($invited, StaffInvitationNotification::class, function (StaffInvitationNotification $notification) use (&$token, $invited): bool {
            $mail = $notification->toMail($invited);
            $token = $notification->token;
            $this->assertSame('دعوة للانضمام إلى فريق كفاءات', $mail->subject);
            $this->assertSame('تعيين كلمة المرور', $mail->actionText);

            return str_contains($mail->actionUrl, $notification->token);
        });

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.users.staff.index', ['status' => 'invited']))
            ->assertOk()
            ->assertSee('مدعو')
            ->assertSee('هدى العمر');

        auth()->logout();

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'huda.staff@example.com',
            'password' => 'New-password-1',
            'password_confirmation' => 'New-password-1',
        ])->assertRedirect(route('login'));

        $invited->refresh();
        $this->assertTrue($invited->is_active);
        $this->assertNotNull($invited->email_verified_at);
        $this->assertFalse(app(StaffInvitationService::class)->isPending($invited));
        $this->assertTrue(Hash::check('New-password-1', $invited->password));

        $this->post('/login', [
            'email' => 'huda.staff@example.com',
            'password' => 'New-password-1',
        ])->assertRedirect(route('verification.notice'));
    }

    public function test_resend_sends_another_link_and_cancel_keeps_the_account_inactive(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $invited = $this->invite($admin, 'ريم الدعو', 'reem.invite@example.com');
        $firstToken = $this->sentToken($invited);

        $this->travel(2)->minutes();

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.users.staff.invitation', $invited), ['action' => 'resend'])
            ->assertRedirect(route('staff-ui.users.staff.index'));

        $secondToken = $this->sentToken($invited);
        $this->assertNotSame($firstToken, $secondToken);

        auth()->logout();

        $this->post(route('password.store'), [
            'token' => $firstToken,
            'email' => 'reem.invite@example.com',
            'password' => 'New-password-1',
            'password_confirmation' => 'New-password-1',
        ])->assertSessionHasErrors('email');
        $this->assertFalse($invited->fresh()->is_active);

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.users.staff.invitation', $invited), ['action' => 'cancel'])
            ->assertRedirect(route('staff-ui.users.staff.index'));

        $invited->refresh();
        $this->assertFalse($invited->is_active);
        $this->assertFalse(app(StaffInvitationService::class)->isPending($invited));
        $this->assertNotNull(User::query()->where('email', 'reem.invite@example.com')->first());

        auth()->logout();

        $this->post(route('password.store'), [
            'token' => $secondToken,
            'email' => 'reem.invite@example.com',
            'password' => 'New-password-1',
            'password_confirmation' => 'New-password-1',
        ])->assertSessionHasErrors('email');

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.users.staff.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('معطّل')
            ->assertSee('ريم الدعو');
    }

    public function test_invite_rejects_an_email_that_already_belongs_to_any_user(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->beneficiary(['email' => 'taken@example.com']);

        $this->actingAsOtpVerified($admin)
            ->from(route('staff-ui.users.staff.index'))
            ->post(route('staff-ui.users.staff.store'), [
                'name' => 'مكرر',
                'email' => 'Taken@example.com',
                'role' => RbacCatalog::ROLE_STAFF,
            ])
            ->assertRedirect(route('staff-ui.users.staff.index'))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::query()->where('email', 'taken@example.com')->count());
        Notification::assertNothingSent();
    }

    public function test_role_change_replaces_the_single_role_and_an_admin_cannot_change_their_own(): void
    {
        $admin = $this->admin();
        $member = $this->staff([], ['name' => 'فهد الدور', 'email' => 'fahad.role@example.com']);

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.users.staff.role', $member), ['role' => RbacCatalog::ROLE_ADMIN])
            ->assertRedirect(route('staff-ui.users.staff.index'));

        $member->refresh();
        $this->assertTrue($member->hasRole(RbacCatalog::ROLE_ADMIN));
        $this->assertFalse($member->hasRole(RbacCatalog::ROLE_STAFF));
        $this->assertSame(RbacCatalog::ROLE_ADMIN, $member->role_type);
        $this->assertCount(1, $member->roles);

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.users.staff.role', $admin), ['role' => RbacCatalog::ROLE_STAFF])
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->hasRole(RbacCatalog::ROLE_ADMIN));

        $audit = AuditLog::query()->where('action', 'user.role_changed')->where('target_user_id', $member->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame(RbacCatalog::ROLE_STAFF, $audit->metadata['old_role']);
        $this->assertSame(RbacCatalog::ROLE_ADMIN, $audit->metadata['new_role']);
    }

    public function test_staff_directory_and_filament_role_changes_grant_the_same_permissions(): void
    {
        $admin = $this->admin();
        $throughDirectory = $this->staff([], ['email' => 'directory-role@example.com']);
        $throughFilament = $this->beneficiary(['email' => 'filament-role@example.com']);
        $throughDirectory->syncPermissions([]);

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.users.staff.role', $throughDirectory), ['role' => RbacCatalog::ROLE_ADMIN])
            ->assertRedirect();

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.users.staff.role', $throughDirectory), ['role' => RbacCatalog::ROLE_STAFF])
            ->assertRedirect();

        UserInlineEditSupport::persistAccountSection($throughFilament, [
            'name' => $throughFilament->name,
            'email' => $throughFilament->email,
            'phone' => $throughFilament->phone,
            'is_active' => true,
            'notify_email' => false,
            'platform_role' => RbacCatalog::ROLE_STAFF,
        ], $admin);

        $expected = collect(PermissionMatrixCatalog::assignablePermissionNames())->sort()->values()->all();
        $directoryPermissions = $throughDirectory->fresh()->getPermissionNames()->sort()->values()->all();
        $filamentPermissions = $throughFilament->fresh()->getPermissionNames()->sort()->values()->all();

        $this->assertSame($expected, $directoryPermissions);
        $this->assertSame($directoryPermissions, $filamentPermissions);
    }

    public function test_invites_stay_hidden_and_blocked_when_the_flag_is_off(): void
    {
        config(['staff_ui.invites_enabled' => false]);
        $admin = $this->admin();
        $invited = $this->staff([], [
            'email' => 'pending-hidden@example.com',
            'is_active' => false,
        ]);
        $invited->forceFill(['remember_token' => StaffInvitationService::MARKER.'pending'])->save();

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.users.staff.index'))
            ->assertOk()
            ->assertDontSee('دعوة موظف')
            ->assertDontSee('إعادة إرسال الدعوة')
            ->assertDontSee('إلغاء الدعوة');

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.users.staff.store'), [
                'name' => 'لا دعوة',
                'email' => 'no-invite@example.com',
                'role' => RbacCatalog::ROLE_STAFF,
            ])
            ->assertForbidden();

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.users.staff.invitation', $invited), ['action' => 'resend'])
            ->assertForbidden();
    }

    public function test_staff_cannot_deactivate_themselves_and_deactivation_logs_out_and_blocks_login(): void
    {
        $admin = $this->admin();
        $member = $this->staff(['users.activate'], [
            'name' => 'سلمان المعطل',
            'email' => 'salman.active@example.com',
        ]);

        DB::table('sessions')->insert([
            'id' => str_repeat('a', 40),
            'user_id' => $member->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode('session'),
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.users.staff.activation', $admin), ['action' => 'deactivate'])
            ->assertForbidden();
        $this->assertTrue($admin->fresh()->is_active);

        $this->actingAsOtpVerified($member)
            ->post(route('staff-ui.users.staff.activation', $member), ['action' => 'deactivate'])
            ->assertForbidden();

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.users.staff.activation', $member), ['action' => 'deactivate'])
            ->assertRedirect(route('staff-ui.users.staff.index'));

        $this->assertFalse($member->fresh()->is_active);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $member->id)->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.deactivated',
            'actor_id' => $admin->id,
            'target_user_id' => $member->id,
        ]);

        auth()->logout();

        $this->post('/login', [
            'email' => 'salman.active@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $reset = Password::broker()->createToken($member);
        $this->post(route('password.store'), [
            'token' => $reset,
            'email' => 'salman.active@example.com',
            'password' => 'New-password-1',
            'password_confirmation' => 'New-password-1',
        ])->assertRedirect(route('login'));
        $this->assertFalse($member->fresh()->is_active);

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.users.staff.activation', $member), ['action' => 'activate'])
            ->assertRedirect(route('staff-ui.users.staff.index'));

        $this->assertTrue($member->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.reactivated',
            'actor_id' => $admin->id,
            'target_user_id' => $member->id,
        ]);

        auth()->logout();

        $this->post('/login', [
            'email' => 'salman.active@example.com',
            'password' => 'New-password-1',
        ])->assertRedirect(route('verification.notice'));
    }

    public function test_permissions_gate_the_staff_list_invite_role_and_activation(): void
    {
        Notification::fake();
        $basic = $this->staff(['beneficiaries.view_basic']);
        $viewer = $this->staff(['users.view']);
        $creator = $this->staff(['users.view', 'users.create']);
        $member = $this->staff([], ['email' => 'target.perms@example.com']);

        $this->actingAsOtpVerified($basic)
            ->get(route('staff-ui.users.index'))
            ->assertOk()
            ->assertDontSee(route('staff-ui.users.staff.index'), false);

        $this->actingAsOtpVerified($basic)
            ->get(route('staff-ui.users.staff.index'))
            ->assertForbidden();

        $this->actingAsOtpVerified($viewer)
            ->post(route('staff-ui.users.staff.store'), [
                'name' => 'بدون إنشاء',
                'email' => 'no-create@example.com',
                'role' => RbacCatalog::ROLE_STAFF,
            ])
            ->assertForbidden();

        $this->actingAsOtpVerified($creator)
            ->post(route('staff-ui.users.staff.store'), [
                'name' => 'يريد أدمن',
                'email' => 'wants-admin@example.com',
                'role' => RbacCatalog::ROLE_ADMIN,
            ])
            ->assertForbidden();

        $this->actingAsOtpVerified($creator)
            ->post(route('staff-ui.users.staff.role', $member), ['role' => RbacCatalog::ROLE_ADMIN])
            ->assertForbidden();

        $this->actingAsOtpVerified($creator)
            ->post(route('staff-ui.users.staff.activation', $member), ['action' => 'deactivate'])
            ->assertForbidden();

        $this->assertTrue($member->fresh()->hasRole(RbacCatalog::ROLE_STAFF));
        $this->assertTrue($member->fresh()->is_active);
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

    private function invite(User $admin, string $name, string $email): User
    {
        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.users.staff.store'), [
                'name' => $name,
                'email' => $email,
                'role' => RbacCatalog::ROLE_STAFF,
            ])
            ->assertRedirect();

        return User::query()->where('email', $email)->firstOrFail();
    }

    private function sentToken(User $user): string
    {
        $sent = Notification::sent($user, StaffInvitationNotification::class);

        return (string) $sent->last()->token;
    }
}
