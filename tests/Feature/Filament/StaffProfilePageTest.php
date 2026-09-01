<?php

namespace Tests\Feature\Filament;

use App\Enums\AccountStatus;
use App\Enums\UserActivityAction;
use App\Filament\Pages\StaffProfilePage;
use App\Models\SecurityLog;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Notifications\EmailChangedSecurityNotice;
use App\Notifications\EmailChangeVerificationCode;
use App\Services\Auth\AccountPasswordChangeService;
use App\Services\Auth\EmailChangeService;
use App\Services\Rbac\RbacCatalog;
use Filament\Facades\Filament;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffProfilePageTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Storage::fake('public');
    }

    public function test_guest_is_redirected_from_profile_page(): void
    {
        $this->get(StaffProfilePage::getUrl())
            ->assertRedirect('/admin/login');
    }

    public function test_beneficiary_cannot_access_profile_page(): void
    {
        $user = User::factory()->create([
            'role_type' => 'beneficiary',
            'email_verified_at' => now(),
        ]);
        $user->assignRole(RbacCatalog::ROLE_BENEFICIARY);

        $this->actingAsOtpVerified($user)
            ->get(StaffProfilePage::getUrl())
            ->assertForbidden();
    }

    public function test_volunteer_cannot_access_profile_page(): void
    {
        $user = User::factory()->create([
            'role_type' => 'volunteer',
            'email_verified_at' => now(),
        ]);
        $user->assignRole(RbacCatalog::ROLE_VOLUNTEER);

        $this->actingAsOtpVerified($user)
            ->get(StaffProfilePage::getUrl())
            ->assertForbidden();
    }

    public function test_inactive_staff_cannot_access_profile_page(): void
    {
        $staff = $this->makeStaff(['is_active' => false]);

        $this->actingAsOtpVerified($staff)
            ->get(StaffProfilePage::getUrl())
            ->assertRedirect(route('login'));
    }

    public function test_staff_without_otp_is_redirected_to_verification(): void
    {
        $staff = $this->makeStaff();

        $this->actingAs($staff)
            ->get(StaffProfilePage::getUrl())
            ->assertRedirect(route('verification.notice'));
    }

    public function test_active_staff_can_access_profile_page(): void
    {
        $staff = $this->makeStaff(['name' => 'موظف نشط']);

        $this->actingAsOtpVerified($staff)
            ->get(StaffProfilePage::getUrl())
            ->assertOk();
    }

    public function test_active_admin_can_access_profile_page(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAsOtpVerified($admin)
            ->get(StaffProfilePage::getUrl())
            ->assertOk();
    }

    public function test_save_updates_name_without_touching_email(): void
    {
        $staff = $this->makeStaff([
            'name' => 'قبل',
            'email' => 'staff-save@example.com',
        ]);

        $this->livewireAsStaff($staff)
            ->fillForm(['name' => 'بعد'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertNotified('تم حفظ الملف الشخصي');

        $staff->refresh();
        $this->assertSame('بعد', $staff->name);
        $this->assertSame('staff-save@example.com', $staff->email);
    }

    public function test_save_rejects_blank_name(): void
    {
        $staff = $this->makeStaff(['name' => 'صالح']);

        $this->livewireAsStaff($staff)
            ->set('data.name', '')
            ->call('save')
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_save_normalizes_phone_number(): void
    {
        $staff = $this->makeStaff(['phone' => null]);

        $this->livewireAsStaff($staff)
            ->fillForm(['phone' => '0501234567'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('+966501234567', $staff->fresh()->phone);
    }

    public function test_save_rejects_invalid_phone(): void
    {
        $staff = $this->makeStaff();

        $this->livewireAsStaff($staff)
            ->fillForm(['phone' => '12345'])
            ->call('save')
            ->assertHasErrors(['phone']);
    }

    public function test_save_allows_clearing_optional_phone(): void
    {
        $staff = $this->makeStaff(['phone' => '+966501234567']);

        $this->livewireAsStaff($staff)
            ->fillForm(['phone' => null])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($staff->fresh()->phone);
    }

    public function test_save_does_not_change_roles_or_permissions(): void
    {
        $staff = $this->makeStaff();
        $roles = $staff->roles->pluck('name')->all();
        $permissions = $staff->getAllPermissions()->pluck('name')->all();

        $this->livewireAsStaff($staff)
            ->fillForm(['name' => 'اسم جديد'])
            ->call('save');

        $staff->refresh()->load('roles');
        $this->assertSame($roles, $staff->roles->pluck('name')->all());
        $this->assertSame($permissions, $staff->getAllPermissions()->pluck('name')->all());
        $this->assertSame('staff', $staff->role_type);
    }

    public function test_general_save_does_not_start_email_change(): void
    {
        Notification::fake();

        $staff = $this->makeStaff(['email' => 'keep@example.com']);

        $this->livewireAsStaff($staff)
            ->set('data.new_email', 'other@example.com')
            ->set('data.new_email_confirmation', 'other@example.com')
            ->fillForm(['name' => $staff->name])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('keep@example.com', $staff->fresh()->email);
        $this->assertDatabaseMissing('pending_email_changes', ['user_id' => $staff->id]);
        Notification::assertNothingSent();
    }

    public function test_request_email_change_sends_otp_without_mutating_users_email(): void
    {
        Notification::fake();

        $staff = $this->makeStaff(['email' => 'staff-old@example.com']);
        $this->clearEmailRateLimits($staff);

        $this->livewireAsStaff($staff)
            ->set('data.new_email', 'staff-new@example.com')
            ->set('data.new_email_confirmation', 'staff-new@example.com')
            ->call('requestEmailChange')
            ->assertHasNoErrors()
            ->assertNotified('تم إرسال رمز التحقق');

        $this->assertSame('staff-old@example.com', $staff->fresh()->email);
        $this->assertDatabaseHas('pending_email_changes', [
            'user_id' => $staff->id,
            'pending_email' => 'staff-new@example.com',
        ]);

        Notification::assertSentOnDemand(EmailChangeVerificationCode::class);
    }

    public function test_request_email_change_rejects_email_in_use(): void
    {
        Notification::fake();

        $this->makeStaff(['email' => 'taken@example.com']);
        $staff = $this->makeStaff(['email' => 'mine@example.com']);
        $this->clearEmailRateLimits($staff);

        $this->livewireAsStaff($staff)
            ->set('data.new_email', 'taken@example.com')
            ->set('data.new_email_confirmation', 'taken@example.com')
            ->call('requestEmailChange')
            ->assertHasErrors(['new_email' => EmailChangeService::MSG_IN_USE]);
    }

    public function test_verify_email_change_updates_email_and_verified_at(): void
    {
        Notification::fake();

        $staff = $this->makeStaff([
            'email' => 'verify-old@example.com',
            'email_verified_at' => now()->subDay(),
        ]);
        $this->clearEmailRateLimits($staff);

        $code = $this->startEmailChangeAndCaptureCode($staff, 'verify-new@example.com');

        $this->livewireAsStaff($staff)
            ->set('data.email_otp', $code)
            ->call('verifyEmailChange')
            ->assertHasNoErrors()
            ->assertNotified(EmailChangeService::MSG_SUCCESS);

        $fresh = $staff->fresh();
        $this->assertSame('verify-new@example.com', $fresh->email);
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertDatabaseMissing('pending_email_changes', ['user_id' => $staff->id]);

        Notification::assertSentOnDemand(EmailChangedSecurityNotice::class, function ($notification, $channels, $notifiable) {
            return ($notifiable->routes['mail'] ?? null) === 'verify-old@example.com';
        });

        $this->assertTrue(
            SecurityLog::query()->where('user_id', $staff->id)->where('event', 'auth.email_change_completed')->exists()
        );
    }

    public function test_verify_rejects_wrong_otp(): void
    {
        Notification::fake();

        $staff = $this->makeStaff(['email' => 'wrong@example.com']);
        $this->clearEmailRateLimits($staff);
        $this->startEmailChangeAndCaptureCode($staff, 'wrong-new@example.com');

        $this->livewireAsStaff($staff)
            ->set('data.email_otp', '000000')
            ->call('verifyEmailChange')
            ->assertHasErrors(['email_otp' => EmailChangeService::MSG_BAD_OTP]);

        $this->assertSame('wrong@example.com', $staff->fresh()->email);
    }

    public function test_cancel_email_change_keeps_current_email(): void
    {
        Notification::fake();

        $staff = $this->makeStaff(['email' => 'cancel@example.com']);
        $this->clearEmailRateLimits($staff);
        $this->startEmailChangeAndCaptureCode($staff, 'cancel-new@example.com');

        $this->livewireAsStaff($staff)
            ->call('cancelEmailChange')
            ->assertNotified('تم إلغاء طلب تغيير البريد');

        $this->assertSame('cancel@example.com', $staff->fresh()->email);
        $this->assertDatabaseMissing('pending_email_changes', ['user_id' => $staff->id]);
    }

    public function test_login_works_with_new_email_after_staff_change(): void
    {
        Notification::fake();

        $staff = $this->makeStaff(['email' => 'login-old@example.com']);
        $this->clearEmailRateLimits($staff);
        $code = $this->startEmailChangeAndCaptureCode($staff, 'login-new@example.com');

        app(EmailChangeService::class)->verify($staff, $code);

        $this->assertTrue(auth()->attempt([
            'email' => 'login-new@example.com',
            'password' => 'password',
        ]));
        auth()->logout();

        $this->assertFalse(auth()->attempt([
            'email' => 'login-old@example.com',
            'password' => 'password',
        ]));
    }

    public function test_change_password_requires_current_password_when_new_provided(): void
    {
        $staff = $this->makeStaff();
        $originalHash = $staff->password;

        $this->livewireAsStaff($staff)
            ->set('data.password', 'NewPassword1!')
            ->set('data.password_confirmation', 'NewPassword1!')
            ->call('changePassword')
            ->assertHasErrors(['current_password']);

        $this->assertSame($originalHash, $staff->fresh()->password);
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $staff = $this->makeStaff();

        $this->livewireAsStaff($staff)
            ->set('data.current_password', 'wrong-password')
            ->set('data.password', 'NewPassword1!')
            ->set('data.password_confirmation', 'NewPassword1!')
            ->call('changePassword')
            ->assertHasErrors(['current_password' => AccountPasswordChangeService::MSG_CURRENT_WRONG]);

        $this->assertTrue(
            SecurityLog::query()->where('user_id', $staff->id)->where('event', 'auth.password_change_failed')->exists()
        );
    }

    public function test_change_password_success_updates_hash_and_invalidates_other_sessions(): void
    {
        config(['session.driver' => 'database']);

        $staff = $this->makeStaff();
        $oldRemember = $staff->remember_token;
        $otherSessionId = str_repeat('c', 40);

        $loginKey = 'login_web_'.sha1(SessionGuard::class);
        DB::table('sessions')->insert([
            'id' => $otherSessionId,
            'user_id' => $staff->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode($loginKey.'|i:'.$staff->id.';'),
            'last_activity' => now()->timestamp,
        ]);

        $this->livewireAsStaff($staff)
            ->set('data.current_password', 'password')
            ->set('data.password', 'NewPassword1!')
            ->set('data.password_confirmation', 'NewPassword1!')
            ->call('changePassword')
            ->assertHasNoErrors()
            ->assertNotified(AccountPasswordChangeService::MSG_SUCCESS);

        $staff->refresh();
        $this->assertTrue(Hash::check('NewPassword1!', $staff->password));
        $this->assertNotSame($oldRemember, $staff->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => $otherSessionId]);
        $this->assertAuthenticatedAs($staff);
        $this->assertTrue(
            SecurityLog::query()->where('user_id', $staff->id)->where('event', 'auth.password_change_completed')->exists()
        );
    }

    public function test_empty_password_fields_do_not_change_hash(): void
    {
        $staff = $this->makeStaff();
        $hash = $staff->password;

        $this->livewireAsStaff($staff)
            ->call('changePassword')
            ->assertHasNoErrors();

        $this->assertSame($hash, $staff->fresh()->password);
    }

    public function test_staff_photo_upload_and_replace(): void
    {
        $staff = $this->makeStaff(['staff_photo' => null]);
        $firstPath = UploadedFile::fake()->image('first.jpg')->store('staff-photos', 'public');

        $this->livewireAsStaff($staff)
            ->fillForm(['staff_photo' => [$firstPath]])
            ->call('save')
            ->assertHasNoErrors();

        $staff->refresh();
        $this->assertSame($firstPath, $staff->staff_photo);
        Storage::disk('public')->assertExists($firstPath);

        $secondPath = UploadedFile::fake()->image('second.jpg')->store('staff-photos', 'public');

        $this->livewireAsStaff($staff)
            ->fillForm(['staff_photo' => [$secondPath]])
            ->call('save')
            ->assertHasNoErrors();

        $staff->refresh();
        $this->assertSame($secondPath, $staff->staff_photo);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    public function test_staff_photo_remove_clears_field(): void
    {
        $path = UploadedFile::fake()->image('avatar.jpg')->store('staff-photos', 'public');
        $staff = $this->makeStaff(['staff_photo' => $path]);

        $this->livewireAsStaff($staff)
            ->fillForm(['staff_photo' => null])
            ->call('save')
            ->assertHasNoErrors();

        $staff->refresh();
        $this->assertNull($staff->staff_photo);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_non_owned_photo_path_is_not_deleted(): void
    {
        Storage::disk('public')->put('images/logo.png', 'logo');
        $staff = $this->makeStaff(['staff_photo' => 'images/logo.png']);

        $this->livewireAsStaff($staff)
            ->fillForm(['staff_photo' => null])
            ->call('save')
            ->assertHasNoErrors();

        Storage::disk('public')->assertExists('images/logo.png');
    }

    public function test_notify_email_toggle_sets_prefs_timestamp_and_preserves_categories(): void
    {
        $staff = $this->makeStaff([
            'notify_email' => false,
            'notification_prefs_set_at' => null,
            'notification_settings' => [
                'categories' => [
                    'account' => ['in_app' => true, 'email' => false],
                ],
                'support_replies_email' => false,
            ],
        ]);

        $this->livewireAsStaff($staff)
            ->fillForm(['notify_email' => true])
            ->call('save')
            ->assertHasNoErrors();

        $staff->refresh();
        $this->assertTrue($staff->notify_email);
        $this->assertNotNull($staff->notification_prefs_set_at);
        $account = $staff->notification_settings['categories']['account'] ?? [];
        $this->assertTrue($account['in_app'] ?? false);
        $this->assertFalse($account['email'] ?? true);

        $this->assertTrue(
            SecurityLog::query()->where('user_id', $staff->id)->where('event', 'staff.notify_email_enabled')->exists()
        );
    }

    public function test_notify_email_unchanged_does_not_log_toggle_event(): void
    {
        $staff = $this->makeStaff([
            'notify_email' => true,
            'notification_prefs_set_at' => now(),
        ]);

        $this->livewireAsStaff($staff)
            ->fillForm(['notify_email' => true])
            ->call('save');

        $this->assertFalse(
            SecurityLog::query()
                ->where('user_id', $staff->id)
                ->whereIn('event', ['staff.notify_email_enabled', 'staff.notify_email_disabled'])
                ->exists()
        );
    }

    public function test_save_ignores_injected_email_password_and_privilege_fields(): void
    {
        $staff = $this->makeStaff([
            'email' => 'locked@example.com',
            'role_type' => 'staff',
            'is_active' => true,
            'account_status' => AccountStatus::Active,
        ]);
        $staff->givePermissionTo(Permission::findOrCreate('manage_programs', 'web'));
        $originalHash = $staff->password;
        $originalRemember = $staff->remember_token;
        $verifiedAt = $staff->email_verified_at;

        $this->livewireAsStaff($staff)
            ->set('data.email', 'hacked@example.com')
            ->set('data.password', 'HackedPass1!')
            ->set('data.password_confirmation', 'HackedPass1!')
            ->set('data.role_type', 'admin')
            ->set('data.is_active', false)
            ->fillForm(['name' => 'اسم محدث'])
            ->call('save')
            ->assertHasNoErrors();

        $staff->refresh();
        $this->assertSame('locked@example.com', $staff->email);
        $this->assertSame($verifiedAt?->toISOString(), $staff->email_verified_at?->toISOString());
        $this->assertSame($originalHash, $staff->password);
        $this->assertSame($originalRemember, $staff->remember_token);
        $this->assertSame('staff', $staff->role_type);
        $this->assertTrue($staff->is_active);
        $this->assertTrue($staff->can('manage_programs'));
    }

    public function test_admin_email_change_writes_generic_activity_log(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin(['email' => 'admin-old@example.com']);
        $this->clearEmailRateLimits($admin);
        $code = $this->startEmailChangeAndCaptureCode($admin, 'admin-new@example.com');

        $this->withSession(['otp_verified' => true]);
        Livewire::actingAs($admin)
            ->test(StaffProfilePage::class)
            ->set('data.email_otp', $code)
            ->call('verifyEmailChange')
            ->assertHasNoErrors();

        $log = UserActivityLog::query()
            ->where('user_id', $admin->id)
            ->where('action', UserActivityAction::EmailChanged)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(EmailChangeService::MSG_ACTIVITY_EMAIL_CHANGED, $log->detail);
        Notification::assertSentOnDemand(EmailChangedSecurityNotice::class);
    }

    public function test_email_change_keeps_current_session_authenticated(): void
    {
        Notification::fake();

        $staff = $this->makeStaff(['email' => 'session-keep@example.com']);
        $this->clearEmailRateLimits($staff);
        $code = $this->startEmailChangeAndCaptureCode($staff, 'session-new@example.com');

        $this->livewireAsStaff($staff)
            ->set('data.email_otp', $code)
            ->call('verifyEmailChange')
            ->assertHasNoErrors();

        $this->actingAsOtpVerified($staff->fresh())
            ->get(StaffProfilePage::getUrl())
            ->assertOk();
    }

    public function test_password_fields_are_cleared_after_success_and_after_wrong_current(): void
    {
        $staff = $this->makeStaff();

        $component = $this->livewireAsStaff($staff)
            ->set('data.current_password', 'password')
            ->set('data.password', 'NewPassword1!')
            ->set('data.password_confirmation', 'NewPassword1!')
            ->call('changePassword')
            ->assertHasNoErrors();

        $this->assertSame('', $component->get('data.current_password'));
        $this->assertSame('', $component->get('data.password'));
        $this->assertSame('', $component->get('data.password_confirmation'));

        $component = $this->livewireAsStaff($staff->fresh())
            ->set('data.current_password', 'wrong')
            ->set('data.password', 'AnotherPass1!')
            ->set('data.password_confirmation', 'AnotherPass1!')
            ->call('changePassword')
            ->assertHasErrors(['current_password']);

        $this->assertSame('', $component->get('data.current_password'));
        $this->assertSame('', $component->get('data.password'));
    }

    public function test_email_otp_fields_cleared_after_successful_verify(): void
    {
        Notification::fake();

        $staff = $this->makeStaff(['email' => 'otp-clear@example.com']);
        $this->clearEmailRateLimits($staff);
        $code = $this->startEmailChangeAndCaptureCode($staff, 'otp-clear-new@example.com');

        $component = $this->livewireAsStaff($staff)
            ->set('data.email_otp', $code)
            ->call('verifyEmailChange')
            ->assertHasNoErrors();

        $this->assertSame('', $component->get('data.email_otp'));
        $this->assertSame('', $component->get('data.new_email'));
    }

    public function test_notification_prefs_modal_not_rendered_after_explicit_save(): void
    {
        $staff = $this->makeStaff([
            'notification_prefs_set_at' => null,
            'notify_email' => false,
        ]);

        $this->livewireAsStaff($staff)
            ->fillForm(['notify_email' => true])
            ->call('save');

        $this->actingAsOtpVerified($staff->fresh())
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('id="notification-prefs-modal"', false);
    }

    public function test_current_session_stays_authenticated_over_http_after_password_change(): void
    {
        $this->useDatabaseSessions();

        $staff = $this->makeStaff();
        $oldRemember = $staff->remember_token;
        $sessionCookie = (string) config('session.cookie');

        $currentSessionId = $this->loginRealSession($staff);

        $this->withCookie($sessionCookie, $currentSessionId)
            ->get(StaffProfilePage::getUrl())
            ->assertOk();

        $otherSessionId = str_repeat('d', 40);
        $this->insertDatabaseSession($otherSessionId, $staff, 'second-browser');

        $this->livewireAsStaff($staff)
            ->set('data.current_password', 'password')
            ->set('data.password', 'NewPassword1!')
            ->set('data.password_confirmation', 'NewPassword1!')
            ->call('changePassword')
            ->assertHasNoErrors();

        $staff->refresh();
        $this->assertTrue(Hash::check('NewPassword1!', $staff->password));
        $this->assertFalse(Hash::check('password', $staff->password));
        $this->assertNotSame($oldRemember, $staff->remember_token);

        // A real request persists the session when it terminates; Livewire's test
        // harness does not run that lifecycle, so write the session out explicitly.
        session()->save();

        $this->withCookie($sessionCookie, $currentSessionId)
            ->get(StaffProfilePage::getUrl())
            ->assertOk();
        $this->assertAuthenticatedAs($staff);
        $this->assertDatabaseHas('sessions', ['id' => $currentSessionId, 'user_id' => $staff->id]);

        // The second browser's session row is gone, so its cookie can no longer be
        // resumed; AccountPasswordChangeServiceTest covers its redirect to login.
        $this->assertDatabaseMissing('sessions', ['id' => $otherSessionId]);
    }

    public function test_failed_user_save_discards_new_photo_and_keeps_previous(): void
    {
        $previousPath = UploadedFile::fake()->image('previous.jpg')->store('staff-photos', 'public');
        $staff = $this->makeStaff(['staff_photo' => $previousPath]);

        $newPath = UploadedFile::fake()->image('replacement.jpg')->store('staff-photos', 'public');
        Storage::disk('public')->assertExists($newPath);

        User::saving(function (): void {
            throw new RuntimeException('forced user save failure');
        });

        try {
            $this->livewireAsStaff($staff)
                ->fillForm([
                    'name' => 'اسم بعد الفشل',
                    'staff_photo' => [$newPath],
                ])
                ->call('save');

            $this->fail('Expected the user save to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced user save failure', $exception->getMessage());
        } finally {
            User::flushEventListeners();
        }

        Storage::disk('public')->assertMissing($newPath);
        Storage::disk('public')->assertExists($previousPath);
        $this->assertSame($previousPath, $staff->fresh()->staff_photo);
    }

    private function livewireAsStaff(User $staff): Testable
    {
        $this->withSession(['otp_verified' => true]);

        return Livewire::actingAs($staff)->test(StaffProfilePage::class);
    }

    private function useDatabaseSessions(): void
    {
        config(['session.driver' => 'database']);

        $this->app->singleton('session', fn ($app) => new SessionManager($app));
        $this->app->singleton('session.store', fn ($app) => $app->make('session')->driver());
    }

    /**
     * Establish a genuinely authenticated, persisted session (login key included)
     * and return its id, mimicking a real browser login.
     */
    private function loginRealSession(User $user): string
    {
        $this->startSession();

        auth()->guard('web')->login($user);
        session()->put('otp_verified', true);
        session()->save();

        $sessionId = (string) session()->getId();
        $this->assertDatabaseHas('sessions', ['id' => $sessionId, 'user_id' => $user->id]);

        return $sessionId;
    }

    private function insertDatabaseSession(string $sessionId, User $user, string $userAgent = 'test'): void
    {
        $loginKey = 'login_web_'.sha1(SessionGuard::class);

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => $userAgent,
            'payload' => base64_encode($loginKey.'|i:'.$user->id.';'),
            'last_activity' => now()->timestamp,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeStaff(array $overrides = []): User
    {
        $staff = User::factory()->create(array_merge([
            'role_type' => 'staff',
            'is_active' => true,
            'account_status' => AccountStatus::Active,
            'email_verified_at' => now(),
        ], $overrides));
        $staff->assignRole(RbacCatalog::ROLE_STAFF);

        return $staff->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeAdmin(array $overrides = []): User
    {
        $admin = User::factory()->create(array_merge([
            'role_type' => 'admin',
            'is_active' => true,
            'account_status' => AccountStatus::Active,
            'email_verified_at' => now(),
        ], $overrides));
        $admin->assignRole(RbacCatalog::ROLE_ADMIN);

        return $admin->fresh();
    }

    private function clearEmailRateLimits(User $user): void
    {
        RateLimiter::clear('email-change-send:'.$user->id);
        RateLimiter::clear('email-change-resend:'.$user->id);
        RateLimiter::clear('email-change-verify:'.$user->id);
    }

    private function startEmailChangeAndCaptureCode(User $user, string $newEmail): string
    {
        Notification::fake();

        app(EmailChangeService::class)->start($user, $newEmail, $newEmail);

        $code = null;
        Notification::assertSentOnDemand(EmailChangeVerificationCode::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        $this->assertNotNull($code);

        return (string) $code;
    }
}
