<?php

namespace Tests\Feature;

use App\Filament\Support\UserInlineEditSupport;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use App\Services\Rbac\PermissionMatrixCatalog;
use App\Services\Rbac\RbacCatalog;
use App\Services\StaffUi\StaffInvitationService;
use App\Support\UserAccountRoleForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
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
            $this->assertTrue(collect($mail->introLines)->contains(fn (string $line): bool => str_contains($line, '72 ساعة')));

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
        $this->assertNull($invited->invited_at);
        $this->assertFalse(app(StaffInvitationService::class)->isPending($invited));
        $this->assertTrue(Hash::check('New-password-1', $invited->password));

        $this->post('/login', [
            'email' => 'huda.staff@example.com',
            'password' => 'New-password-1',
        ])->assertRedirect(route('verification.notice'));
    }

    public function test_resend_sends_another_link_and_cancel_deletes_an_unused_account(): void
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

        $this->assertNull(User::query()->where('email', 'reem.invite@example.com')->first());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'staff.invitation_cancelled',
            'actor_id' => $admin->id,
        ]);

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
            ->assertDontSee('ريم الدعو');
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
        $invited->forceFill(['invited_at' => now(), 'is_active' => false])->save();

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

    public function test_accepted_invitation_gets_the_same_permissions_as_filament_staff_creation(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $invited = $this->invite($admin, 'سلمى الصلاحيات', 'salma.perms@example.com');
        $token = $this->sentToken($invited);

        auth()->logout();

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'salma.perms@example.com',
            'password' => 'New-password-1',
            'password_confirmation' => 'New-password-1',
        ])->assertRedirect(route('login'));

        $created = User::factory()->create([
            'email' => 'filament.staff@example.com',
            'role_type' => RbacCatalog::ROLE_STAFF,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $created->syncRoles([RbacCatalog::ROLE_STAFF]);
        UserAccountRoleForm::applyRoleSideEffects($created, RbacCatalog::ROLE_STAFF);

        $expected = collect(PermissionMatrixCatalog::assignablePermissionNames())->sort()->values()->all();

        $this->assertSame($expected, $invited->fresh()->getPermissionNames()->sort()->values()->all());
        $this->assertSame($expected, $created->fresh()->getPermissionNames()->sort()->values()->all());
    }

    public function test_cancel_keeps_an_invited_account_that_has_records_and_blocks_manual_activation(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $invited = $this->invite($admin, 'ليان السجل', 'layan.records@example.com');
        $invited->profile()->create([]);

        $this->actingAsOtpVerified($admin)
            ->from(route('staff-ui.users.staff.show', $invited))
            ->post(route('staff-ui.users.staff.invitation', $invited), ['action' => 'cancel'])
            ->assertRedirect(route('staff-ui.users.staff.show', $invited));

        $invited->refresh();
        $this->assertFalse($invited->is_active);
        $this->assertNull($invited->invited_at);
        $this->assertTrue(app(StaffInvitationService::class)->requiresReinvite($invited));

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.users.staff.show', $invited))
            ->assertOk()
            ->assertSee('إعادة الدعوة')
            ->assertDontSee('تفعيل الحساب');

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.users.staff.activation', $invited), ['action' => 'activate'])
            ->assertStatus(422);

        $this->actingAsOtpVerified($admin)
            ->post(route('staff-ui.users.staff.invitation', $invited), ['action' => 'reinvite'])
            ->assertRedirect();

        $this->assertTrue(app(StaffInvitationService::class)->isPending($invited->fresh()));
        Notification::assertSentTo($invited, StaffInvitationNotification::class);
    }

    public function test_invitation_link_lasts_seventy_two_hours_and_password_reset_stays_at_one_hour(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $invited = $this->invite($admin, 'مدة الدعوة', 'invite.ttl@example.com');
        $token = $this->sentToken($invited);

        $this->assertSame(60, (int) config('auth.passwords.users.expire'));
        $this->assertSame(72 * 60, (int) config('auth.passwords.staff_invitations.expire'));

        $this->travel(71)->hours();
        auth()->logout();

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'invite.ttl@example.com',
            'password' => 'New-password-1',
            'password_confirmation' => 'New-password-1',
        ])->assertRedirect(route('login'));

        $this->assertNull($invited->fresh()->invited_at);

        $active = $this->staff([], ['email' => 'reset.ttl@example.com']);
        $reset = Password::broker('users')->createToken($active);
        $this->travel(61)->minutes();

        $this->post(route('password.store'), [
            'token' => $reset,
            'email' => 'reset.ttl@example.com',
            'password' => 'New-password-1',
            'password_confirmation' => 'New-password-1',
        ])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $active->fresh()->password));
    }

    public function test_invitation_link_expires_after_seventy_two_hours(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $invited = $this->invite($admin, 'دعوة منتهية', 'invite.expired@example.com');
        $token = $this->sentToken($invited);

        $this->travel(73)->hours();
        auth()->logout();

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'invite.expired@example.com',
            'password' => 'New-password-1',
            'password_confirmation' => 'New-password-1',
        ])->assertSessionHasErrors('email');

        $this->assertFalse($invited->fresh()->is_active);
        $this->assertNotNull($invited->fresh()->invited_at);
    }

    public function test_legacy_invite_marker_is_copied_into_invited_at(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
            'email_verified_at' => null,
            'invited_at' => null,
        ]);

        Schema::table('users', function ($table): void {
            $table->dropColumn('invited_at');
        });
        DB::table('migrations')->where('migration', '2026_10_07_080000_add_invited_at_to_users_table')->delete();
        DB::table('users')->where('id', $user->id)->update([
            'remember_token' => 'staff-invite:legacy',
        ]);

        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_10_07_080000_add_invited_at_to_users_table.php',
            '--force' => true,
        ])->assertSuccessful();

        $row = DB::table('users')->where('id', $user->id)->first();
        $this->assertNotNull($row->invited_at);
        $this->assertFalse(str_starts_with((string) $row->remember_token, 'staff-invite:'));
        $this->assertTrue(app(StaffInvitationService::class)->isPending($user->fresh()));
    }

    public function test_the_last_active_admin_cannot_be_demoted_or_deactivated(): void
    {
        config(['app.admin_email' => 'keeper@example.com']);
        $only = $this->admin(['email' => 'only.admin@example.com']);
        $actor = $this->staff(['users.activate']);
        $this->assertFalse($only->isProtectedAdminUser());

        try {
            UserAccountRoleForm::syncAssignedRole($actor, $only, RbacCatalog::ROLE_STAFF);
            $this->fail('Demoting the last active admin was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame('لا يمكن تنزيل آخر مدير نشط.', $exception->errors()['role'][0]);
        }

        $this->assertTrue($only->fresh()->isAdmin());

        $this->actingAsOtpVerified($actor)
            ->from(route('staff-ui.users.staff.show', $only))
            ->post(route('staff-ui.users.staff.activation', $only), ['action' => 'deactivate'])
            ->assertRedirect(route('staff-ui.users.staff.show', $only))
            ->assertSessionHasErrors('activation');

        $this->assertTrue($only->fresh()->is_active);

        $extra = $this->admin(['email' => 'extra.admin@example.com']);

        $this->actingAsOtpVerified($extra)
            ->post(route('staff-ui.users.staff.role', $only), ['role' => RbacCatalog::ROLE_STAFF])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($only->fresh()->hasRole(RbacCatalog::ROLE_STAFF));

        $protected = $this->admin(['email' => 'keeper@example.com']);
        $this->assertTrue($protected->isProtectedAdminUser());

        $this->actingAsOtpVerified($extra)
            ->from(route('staff-ui.users.staff.show', $protected))
            ->post(route('staff-ui.users.staff.role', $protected), ['role' => RbacCatalog::ROLE_STAFF])
            ->assertRedirect(route('staff-ui.users.staff.show', $protected))
            ->assertSessionHasErrors('role');

        $this->actingAsOtpVerified($extra)
            ->post(route('staff-ui.users.staff.activation', $protected), ['action' => 'deactivate'])
            ->assertForbidden();

        $this->assertTrue($protected->fresh()->isAdmin());
        $this->assertTrue($protected->fresh()->is_active);
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
