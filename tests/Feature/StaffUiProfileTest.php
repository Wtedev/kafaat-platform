<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EmailChangeVerificationCode;
use App\Services\Auth\AccountPasswordChangeService;
use App\Services\Auth\EmailChangeService;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiProfileTest extends TestCase
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
            'staff_ui.ready_modules' => ['shell'],
        ]);
    }

    public function test_guest_is_redirected_and_trainees_are_forbidden(): void
    {
        $this->get(route('staff-ui.profile'))->assertRedirect(route('login'));

        $trainee = User::factory()->create([
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $trainee->assignRole(RbacCatalog::ROLE_BENEFICIARY);

        $this->actingAsOtpVerified($trainee)
            ->get(route('staff-ui.profile'))
            ->assertForbidden();
    }

    public function test_staff_without_otp_is_sent_to_verification(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->get(route('staff-ui.profile'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_profile_page_links_from_the_shell_and_reuses_the_account_fields(): void
    {
        $admin = $this->admin(['name' => 'مدير النظام', 'email' => 'admin-profile@example.com']);

        $this->actingAsOtpVerified($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee(route('staff-ui.profile'), false);

        $this->actingAsOtpVerified($admin)
            ->get(route('staff-ui.profile'))
            ->assertOk()
            ->assertSee('الملف الشخصي')
            ->assertSee('admin-profile@example.com')
            ->assertSee('الاسم')
            ->assertSee('رقم الجوال')
            ->assertSee('الصورة الشخصية')
            ->assertSee('إرسال رمز التحقق')
            ->assertSee('تحديث كلمة المرور')
            ->assertDontSee('aria-current="page"', false);
    }

    public function test_maintenance_hides_the_profile_from_staff(): void
    {
        config([
            'staff_ui.maintenance' => true,
            'staff_ui.ready_modules' => [],
        ]);

        $this->actingAsOtpVerified($this->staff())
            ->get(route('staff-ui.profile'))
            ->assertForbidden();

        $this->actingAsOtpVerified($this->admin())
            ->get(route('staff-ui.profile'))
            ->assertOk();
    }

    public function test_save_updates_name_and_phone_without_touching_email_or_privileges(): void
    {
        $staff = $this->staff([
            'name' => 'قبل',
            'email' => 'locked@example.com',
            'phone' => null,
            'role_type' => 'staff',
        ]);
        $hash = $staff->password;

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.profile.update'), [
                'name' => 'بعد',
                'phone' => '0501234567',
                'email' => 'hacked@example.com',
                'password' => 'HackedPass1!',
                'role_type' => 'admin',
                'new_email' => 'other@example.com',
                'new_email_confirmation' => 'other@example.com',
            ])
            ->assertRedirect(route('staff-ui.profile'))
            ->assertSessionHas('status', 'تم حفظ الملف الشخصي');

        $staff->refresh();
        $this->assertSame('بعد', $staff->name);
        $this->assertSame('+966501234567', $staff->phone);
        $this->assertSame('locked@example.com', $staff->email);
        $this->assertSame($hash, $staff->password);
        $this->assertSame('staff', $staff->role_type);
        $this->assertDatabaseMissing('pending_email_changes', ['user_id' => $staff->id]);
    }

    public function test_save_rejects_blank_name_and_invalid_phone(): void
    {
        $staff = $this->staff(['name' => 'صالح', 'phone' => null]);

        $this->actingAsOtpVerified($staff)
            ->from(route('staff-ui.profile'))
            ->post(route('staff-ui.profile.update'), ['name' => '   ', 'phone' => ''])
            ->assertRedirect(route('staff-ui.profile'))
            ->assertSessionHasErrors('name');

        $this->assertSame('صالح', $staff->fresh()->name);

        $this->actingAsOtpVerified($staff)
            ->from(route('staff-ui.profile'))
            ->post(route('staff-ui.profile.update'), ['name' => 'صالح', 'phone' => '12345'])
            ->assertRedirect(route('staff-ui.profile'))
            ->assertSessionHasErrors('phone');

        $this->assertNull($staff->fresh()->phone);
    }

    public function test_photo_upload_replaces_the_previous_file_and_rejects_other_types(): void
    {
        Storage::fake('public');
        $staff = $this->staff(['staff_photo' => null]);

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.profile.update'), [
                'name' => $staff->name,
                'staff_photo' => UploadedFile::fake()->image('first.jpg'),
            ])
            ->assertRedirect(route('staff-ui.profile'));

        $first = $staff->fresh()->staff_photo;
        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first);

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.profile.update'), [
                'name' => $staff->name,
                'staff_photo' => UploadedFile::fake()->image('second.png'),
            ])
            ->assertRedirect(route('staff-ui.profile'));

        $second = $staff->fresh()->staff_photo;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->actingAsOtpVerified($staff)
            ->from(route('staff-ui.profile'))
            ->post(route('staff-ui.profile.update'), [
                'name' => $staff->name,
                'staff_photo' => UploadedFile::fake()->create('avatar.svg', 20, 'image/svg+xml'),
            ])
            ->assertRedirect(route('staff-ui.profile'))
            ->assertSessionHasErrors('staff_photo');

        $this->assertSame($second, $staff->fresh()->staff_photo);
    }

    public function test_removing_a_photo_clears_owned_files_and_keeps_shared_assets(): void
    {
        Storage::fake('public');
        $owned = UploadedFile::fake()->image('avatar.jpg')->store('staff-photos', 'public');
        $staff = $this->staff(['staff_photo' => $owned]);

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.profile.update'), [
                'name' => $staff->name,
                'remove_staff_photo' => '1',
            ])
            ->assertRedirect(route('staff-ui.profile'));

        $this->assertNull($staff->fresh()->staff_photo);
        Storage::disk('public')->assertMissing($owned);

        Storage::disk('public')->put('images/logo.png', 'logo');
        $staff->forceFill(['staff_photo' => 'images/logo.png'])->save();

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.profile.update'), [
                'name' => $staff->name,
                'remove_staff_photo' => '1',
            ])
            ->assertRedirect(route('staff-ui.profile'));

        $this->assertNull($staff->fresh()->staff_photo);
        Storage::disk('public')->assertExists('images/logo.png');
    }

    public function test_email_change_requires_otp_and_keeps_the_session(): void
    {
        Notification::fake();
        $staff = $this->staff(['email' => 'staff-old@example.com']);
        $this->clearEmailRateLimits($staff);

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.profile.email'), [
                'new_email' => 'taken@example.com',
                'new_email_confirmation' => 'other@example.com',
            ])
            ->assertSessionHasErrors('new_email_confirmation');
        $this->assertSame('staff-old@example.com', $staff->fresh()->email);

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.profile.email'), [
                'new_email' => 'staff-new@example.com',
                'new_email_confirmation' => 'staff-new@example.com',
            ])
            ->assertRedirect(route('staff-ui.profile'))
            ->assertSessionHas('status', 'تم إرسال رمز التحقق');

        $this->assertSame('staff-old@example.com', $staff->fresh()->email);
        $code = null;
        Notification::assertSentOnDemand(EmailChangeVerificationCode::class, function ($notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        $this->actingAsOtpVerified($staff)
            ->from(route('staff-ui.profile'))
            ->post(route('staff-ui.profile.email.verify'), ['email_otp' => '000000'])
            ->assertRedirect(route('staff-ui.profile'))
            ->assertSessionHasErrors('email_otp');
        $this->assertSame('staff-old@example.com', $staff->fresh()->email);

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.profile.email.verify'), ['email_otp' => $code])
            ->assertRedirect(route('staff-ui.profile'))
            ->assertSessionHas('status', EmailChangeService::MSG_SUCCESS);

        $this->assertSame('staff-new@example.com', $staff->fresh()->email);
        $this->assertAuthenticatedAs($staff);
        $this->assertDatabaseMissing('pending_email_changes', ['user_id' => $staff->id]);
    }

    public function test_cancel_email_change_keeps_the_current_address(): void
    {
        Notification::fake();
        $staff = $this->staff(['email' => 'keep@example.com']);
        $this->clearEmailRateLimits($staff);

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.profile.email'), [
                'new_email' => 'next@example.com',
                'new_email_confirmation' => 'next@example.com',
            ])
            ->assertRedirect(route('staff-ui.profile'));

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.profile.email.cancel'))
            ->assertRedirect(route('staff-ui.profile'))
            ->assertSessionHas('status', 'تم إلغاء طلب تغيير البريد');

        $this->assertSame('keep@example.com', $staff->fresh()->email);
        $this->assertDatabaseMissing('pending_email_changes', ['user_id' => $staff->id]);
    }

    public function test_password_change_checks_the_current_password_and_ignores_empty_fields(): void
    {
        $staff = $this->staff();
        $hash = $staff->password;

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.profile.password'), [
                'current_password' => '',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('staff-ui.profile'));
        $this->assertSame($hash, $staff->fresh()->password);

        $this->actingAsOtpVerified($staff)
            ->from(route('staff-ui.profile'))
            ->post(route('staff-ui.profile.password'), [
                'current_password' => 'wrong-password',
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ])
            ->assertRedirect(route('staff-ui.profile'))
            ->assertSessionHasErrors('current_password');
        $this->assertSame($hash, $staff->fresh()->password);

        $this->actingAsOtpVerified($staff)
            ->post(route('staff-ui.profile.password'), [
                'current_password' => 'password',
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ])
            ->assertRedirect(route('staff-ui.profile'))
            ->assertSessionHas('status', AccountPasswordChangeService::MSG_SUCCESS);

        $this->assertTrue(Hash::check('NewPassword1', $staff->fresh()->password));
        $this->assertAuthenticatedAs($staff);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function staff(array $overrides = []): User
    {
        $staff = User::factory()->create(array_merge([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $overrides));
        $staff->assignRole(RbacCatalog::ROLE_STAFF);

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

    private function clearEmailRateLimits(User $user): void
    {
        RateLimiter::clear('email-change-send:'.$user->id);
        RateLimiter::clear('email-change-resend:'.$user->id);
        RateLimiter::clear('email-change-verify:'.$user->id);
    }
}
