<?php

namespace Tests\Feature\Auth;

use App\Filament\Pages\Auth\Login as FilamentLogin;
use App\Filament\Support\UserInlineEditSupport;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\VerifyEmailCode;
use App\Services\Auth\EmailChangeService;
use App\Support\Auth\EmailNormalizer;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class EmailCaseNormalizationAuthTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        // Array cache persists across tests in-process; clear login buckets.
        RateLimiter::clear(EmailNormalizer::normalize('rate.limit@example.com').'|127.0.0.1');
    }

    public function test_login_with_lowercase_stored_and_uppercase_input(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('CorrectPass1!'),
            'is_active' => true,
            'role_type' => 'beneficiary',
        ]);
        $user->assignRole('beneficiary');

        $this->assertSame('user@example.com', $user->fresh()->email);

        $this->post(route('login'), [
            'email' => 'USER@EXAMPLE.COM',
            'password' => 'CorrectPass1!',
        ])->assertRedirect(route('verification.notice'));

        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmailCode::class);
    }

    public function test_login_with_legacy_mixed_case_stored_and_lowercase_input(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'legacy-mixed@example.com',
            'password' => Hash::make('CorrectPass1!'),
            'is_active' => true,
            'role_type' => 'beneficiary',
        ]);
        $user->assignRole('beneficiary');

        // Bypass mutator to simulate a legacy mixed-case row.
        DB::table('users')->where('id', $user->id)->update(['email' => 'Legacy.Mixed@Example.com']);
        $user->refresh();
        $this->assertSame('Legacy.Mixed@Example.com', $user->email);

        $this->post(route('login'), [
            'email' => 'legacy.mixed@example.com',
            'password' => 'CorrectPass1!',
        ])->assertRedirect(route('verification.notice'));

        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmailCode::class);
        // OTP must address the stored mailbox, not the typed casing.
        Notification::assertSentTo($user, VerifyEmailCode::class, function (VerifyEmailCode $notification, array $channels, object $notifiable): bool {
            return EmailNormalizer::equals((string) $notifiable->email, 'Legacy.Mixed@Example.com');
        });
    }

    public function test_login_trims_surrounding_whitespace(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'trim-me@example.com',
            'password' => Hash::make('CorrectPass1!'),
            'is_active' => true,
            'role_type' => 'beneficiary',
        ]);
        $user->assignRole('beneficiary');

        $this->post(route('login'), [
            'email' => '  trim-me@example.com  ',
            'password' => 'CorrectPass1!',
        ])->assertRedirect(route('verification.notice'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_when_password_case_differs(): void
    {
        User::factory()->create([
            'email' => 'case-pass@example.com',
            'password' => Hash::make('CorrectPass1!'),
            'is_active' => true,
        ]);

        $this->post(route('login'), [
            'email' => 'CASE-PASS@EXAMPLE.COM',
            'password' => 'correctpass1!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_filament_login_with_different_email_case(): void
    {
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $admin = User::factory()->create([
            'email' => 'admin.case@example.com',
            'password' => Hash::make('AdminPass1!'),
            'is_active' => true,
            'role_type' => 'admin',
        ]);
        $admin->assignRole('admin');

        Livewire::test(FilamentLogin::class)
            ->fillForm([
                'email' => 'ADMIN.CASE@EXAMPLE.COM',
                'password' => 'AdminPass1!',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($admin);
        Notification::assertSentTo($admin, VerifyEmailCode::class);
    }

    public function test_password_reset_request_and_complete_with_different_email_case(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset.me@example.com',
            'password' => Hash::make('OldPass1!'),
            'is_active' => true,
        ]);

        $this->post(route('password.email'), [
            'email' => 'RESET.ME@EXAMPLE.COM',
        ])->assertSessionHasNoErrors();

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $this->assertNotNull($token);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'Reset.Me@Example.com',
            'password' => 'NewPass1!',
            'password_confirmation' => 'NewPass1!',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NewPass1!', $user->fresh()->password));

        Notification::fake();
        $this->post(route('login'), [
            'email' => 'reset.me@example.com',
            'password' => 'NewPass1!',
        ])->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_cannot_create_second_account_with_same_email_different_case(): void
    {
        User::factory()->create([
            'email' => 'unique.user@example.com',
            'password' => Hash::make('SecretPass1!'),
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        User::factory()->create([
            'email' => 'UNIQUE.USER@EXAMPLE.COM',
            'password' => Hash::make('OtherPass1!'),
        ]);
    }

    public function test_email_change_rejects_case_insensitive_duplicate(): void
    {
        $holder = User::factory()->create([
            'email' => 'taken.address@example.com',
            'password' => Hash::make('SecretPass1!'),
            'is_active' => true,
            'role_type' => 'beneficiary',
        ]);
        $actor = User::factory()->create([
            'email' => 'changer@example.com',
            'password' => Hash::make('SecretPass1!'),
            'is_active' => true,
            'role_type' => 'beneficiary',
        ]);

        $result = app(EmailChangeService::class)->start(
            $actor,
            'TAKEN.ADDRESS@EXAMPLE.COM',
            'TAKEN.ADDRESS@EXAMPLE.COM',
        );

        $this->assertFalse($result['ok']);
        $this->assertSame(EmailChangeService::MSG_IN_USE, $result['message']);
        $this->assertSame('taken.address@example.com', $holder->fresh()->email);
        $this->assertSame('changer@example.com', $actor->fresh()->email);
    }

    public function test_admin_edit_rejects_case_insensitive_duplicate_email(): void
    {
        $admin = User::factory()->create([
            'email' => 'editor-admin@example.com',
            'password' => Hash::make('SecretPass1!'),
            'is_active' => true,
            'role_type' => 'admin',
        ]);
        $admin->assignRole('admin');

        User::factory()->create([
            'email' => 'occupied@example.com',
            'password' => Hash::make('SecretPass1!'),
            'is_active' => true,
            'role_type' => 'beneficiary',
        ]);

        $target = User::factory()->create([
            'email' => 'free.target@example.com',
            'password' => Hash::make('SecretPass1!'),
            'is_active' => true,
            'role_type' => 'beneficiary',
        ]);

        try {
            UserInlineEditSupport::persistAccountSection($target, [
                'name' => $target->name,
                'email' => 'OCCUPIED@EXAMPLE.COM',
                'phone' => $target->phone,
                'is_active' => true,
                'notify_email' => true,
                'platform_role' => 'beneficiary',
            ], $admin);
            $this->fail('Expected validation exception for duplicate email.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        $this->assertSame('free.target@example.com', $target->fresh()->email);
    }

    public function test_rate_limiting_keys_are_unified_across_email_case(): void
    {
        User::factory()->create([
            'email' => 'rate.limit@example.com',
            'password' => Hash::make('SecretPass1!'),
            'is_active' => true,
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('login'))->post(route('login'), [
                'email' => 'rate.limit@example.com',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        // Same mailbox, different casing — must share the limiter bucket.
        $this->from(route('login'))->post(route('login'), [
            'email' => 'RATE.LIMIT@EXAMPLE.COM',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors([
            'email' => 'لقد تجاوزت عدد المحاولات المسموح بها. حاول مجدداً بعد دقيقة.',
        ]);

        $this->assertGuest();
    }

    public function test_otp_continues_to_work_and_sends_to_correct_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'otp.user@example.com',
            'password' => Hash::make('CorrectPass1!'),
            'is_active' => true,
            'role_type' => 'beneficiary',
        ]);
        $user->assignRole('beneficiary');

        $this->post(route('login'), [
            'email' => 'OTP.USER@EXAMPLE.COM',
            'password' => 'CorrectPass1!',
        ])->assertRedirect(route('verification.notice'));

        Notification::assertSentTo($user, VerifyEmailCode::class);
        $this->assertSame('otp.user@example.com', $user->fresh()->email);

        $code = '123456';
        EmailVerificationCode::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(15),
            ],
        );

        $this->actingAs($user)
            ->post(route('verification.verify'), ['code' => $code])
            ->assertRedirect();

        $this->assertTrue((bool) session('otp_verified'));
    }

    public function test_user_mutator_stores_normalized_email(): void
    {
        $user = User::factory()->create([
            'email' => '  Mixed.Case@Example.COM  ',
            'password' => Hash::make('SecretPass1!'),
        ]);

        $this->assertSame('mixed.case@example.com', $user->fresh()->email);
    }
}
