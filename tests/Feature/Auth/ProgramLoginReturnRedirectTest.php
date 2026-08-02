<?php

namespace Tests\Feature\Auth;

use App\Enums\ProgramStatus;
use App\Models\Profile;
use App\Models\ProgramRegistration;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Notifications\VerifyEmailCode;
use App\Support\Auth\SafeLoginReturnUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class ProgramLoginReturnRedirectTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
    }

    public function test_guest_program_login_cta_includes_return_uri(): void
    {
        $program = $this->makeOpenProgram('cta-program');

        $this->get(route('public.programs.show', $program).'?ref=hero')
            ->assertOk()
            ->assertSee(
                'href="'.e(route('login', ['return' => '/programs/cta-program?ref=hero'])).'"',
                false,
            );
    }

    public function test_guest_program_login_cta_is_grouped_with_prompt_not_space_between(): void
    {
        $program = $this->makeOpenProgram('cta-layout');

        $html = $this->get(route('public.programs.show', $program))
            ->assertOk()
            ->assertSee('يجب تسجيل الدخول للتسجيل في البرنامج.', false)
            ->assertSee('سجّل الدخول للتسجيل', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/items-end gap-3 sm:flex-row sm:items-center sm:justify-end[^>]*>\s*<p[^>]*>يجب تسجيل الدخول للتسجيل في البرنامج\.<\/p>/u',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/sm:justify-between[^>]*>\s*<p[^>]*>يجب تسجيل الدخول للتسجيل في البرنامج\.<\/p>/u',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/justify-center[^>]*>\s*<p[^>]*>يجب تسجيل الدخول للتسجيل في البرنامج\.<\/p>/u',
            $html,
        );
        $this->assertStringContainsString('ltr:rotate-180', $html);
    }

    public function test_guest_login_cta_renders_after_partners_on_volunteer_leaders(): void
    {
        $program = $this->makeOpenProgram('cta-below-partners');
        $program->update(['title' => 'قادة التطوع — اختبار ترتيب التسجيل']);

        $html = $this->get(route('public.programs.show', $program))
            ->assertOk()
            ->assertSee('شركاء البرنامج', false)
            ->assertSee('يجب تسجيل الدخول للتسجيل في البرنامج.', false)
            ->assertSee('سجّل الدخول للتسجيل', false)
            ->getContent();

        $partnersPos = strpos($html, 'id="program-partners-heading"');
        $ctaPos = strpos($html, 'يجب تسجيل الدخول للتسجيل في البرنامج.');
        $this->assertNotFalse($partnersPos);
        $this->assertNotFalse($ctaPos);
        // PHPUnit: assertLessThan($expected, $actual) ⇒ $actual < $expected
        $this->assertLessThan($ctaPos, $partnersPos);

        $this->assertMatchesRegularExpression(
            '/items-end gap-3 sm:flex-row sm:items-center sm:justify-end[^>]*>\s*<p[^>]*>يجب تسجيل الدخول للتسجيل في البرنامج\.<\/p>/u',
            $html,
        );

        // Below partners: action slot must not sit in a white rounded card wrapper.
        $this->assertDoesNotMatchRegularExpression(
            '/rounded-2xl bg-white[^>]*>\s*<div class="flex flex-col gap-4">\s*<div class="flex flex-col items-end/u',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/overflow-hidden rounded-2xl bg-white px-6 py-6/u',
            $html,
        );
    }

    public function test_beneficiary_returns_to_program_after_login_and_otp(): void
    {
        Notification::fake();

        $program = $this->makeOpenProgram('return-program-a');
        $user = $this->makeBeneficiary('return-a@example.com');

        $this->get(route('login', ['return' => '/programs/return-program-a']))
            ->assertOk()
            ->assertSessionHas(SafeLoginReturnUrl::SESSION_KEY, '/programs/return-program-a');

        $this->post(route('login'), [
            'email' => 'return-a@example.com',
            'password' => 'CorrectPass1!',
        ])->assertRedirect(route('verification.notice'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(
            '/programs/return-program-a',
            session(SafeLoginReturnUrl::SESSION_KEY),
        );

        $code = $this->latestOtpCode($user);

        $this->post(route('verification.verify'), ['code' => $code])
            ->assertRedirect('/programs/return-program-a')
            ->assertSessionHas('otp_verified', true)
            ->assertSessionMissing(SafeLoginReturnUrl::SESSION_KEY);

        $this->assertSame(0, ProgramRegistration::query()->where('user_id', $user->id)->count());
    }

    public function test_beneficiary_returns_to_program_preserving_query_parameters(): void
    {
        Notification::fake();

        $this->makeOpenProgram('return-program-b');
        $user = $this->makeBeneficiary('return-b@example.com');
        $return = '/programs/return-program-b?utm_source=share&ref=card';

        $this->get(route('login', ['return' => $return]))->assertOk();

        $this->post(route('login'), [
            'email' => 'return-b@example.com',
            'password' => 'CorrectPass1!',
        ])->assertRedirect(route('verification.notice'));

        $code = $this->latestOtpCode($user);

        $this->post(route('verification.verify'), ['code' => $code])
            ->assertRedirect($return);
    }

    public function test_direct_login_without_return_goes_to_portal_dashboard(): void
    {
        Notification::fake();

        $user = $this->makeBeneficiary('direct@example.com');

        $this->get(route('login'))->assertOk()
            ->assertSessionMissing(SafeLoginReturnUrl::SESSION_KEY);

        $this->post(route('login'), [
            'email' => 'direct@example.com',
            'password' => 'CorrectPass1!',
        ])->assertRedirect(route('verification.notice'));

        $code = $this->latestOtpCode($user);

        $this->post(route('verification.verify'), ['code' => $code])
            ->assertRedirect(route('portal.dashboard'));
    }

    public function test_external_return_url_is_ignored_and_defaults_to_portal(): void
    {
        Notification::fake();

        $this->makeOpenProgram('safe-external');
        $user = $this->makeBeneficiary('external@example.com');

        $this->get(route('login', ['return' => 'https://evil.example/phish']))
            ->assertOk()
            ->assertSessionMissing(SafeLoginReturnUrl::SESSION_KEY);

        $this->withSession(['url.intended' => 'https://evil.example/phish'])
            ->post(route('login'), [
                'email' => 'external@example.com',
                'password' => 'CorrectPass1!',
            ])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionMissing('url.intended');

        $code = $this->latestOtpCode($user);

        $this->post(route('verification.verify'), ['code' => $code])
            ->assertRedirect(route('portal.dashboard'));
    }

    public function test_failed_login_preserves_program_return_for_retry(): void
    {
        Notification::fake();

        $this->makeOpenProgram('retry-program');
        $user = $this->makeBeneficiary('retry@example.com');

        $this->get(route('login', ['return' => '/programs/retry-program']))
            ->assertSessionHas(SafeLoginReturnUrl::SESSION_KEY, '/programs/retry-program');

        $this->from(route('login', ['return' => '/programs/retry-program']))
            ->post(route('login'), [
                'email' => 'retry@example.com',
                'password' => 'WrongPass1!',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('email')
            ->assertSessionHas(SafeLoginReturnUrl::SESSION_KEY, '/programs/retry-program');

        $this->post(route('login'), [
            'email' => 'retry@example.com',
            'password' => 'CorrectPass1!',
        ])->assertRedirect(route('verification.notice'));

        $code = $this->latestOtpCode($user);

        $this->post(route('verification.verify'), ['code' => $code])
            ->assertRedirect('/programs/retry-program');
    }

    public function test_authenticated_beneficiary_still_sees_register_form_not_login_cta(): void
    {
        $program = $this->makeOpenProgram('already-auth');
        $user = $this->makeBeneficiary('already@example.com');

        $this->actingAs($user)
            ->withSession(['otp_verified' => true])
            ->get(route('public.programs.show', $program))
            ->assertOk()
            ->assertSee('سجّل في البرنامج')
            ->assertDontSee('سجّل الدخول للتسجيل', false);
    }

    public function test_guest_sees_closed_registration_message_not_login_cta_on_volunteer_leaders(): void
    {
        $program = TrainingProgram::query()->create([
            'title' => 'قادة التطوع',
            'slug' => 'vl-registration-closed',
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
            'registration_start' => now()->subDays(10)->toDateString(),
            'registration_end' => now()->subDay()->toDateString(),
        ]);

        $this->assertFalse($program->isRegistrationOpen());

        $this->get(route('public.programs.show', $program))
            ->assertOk()
            ->assertSee('انتهى التسجيل في هذا البرنامج.', false)
            ->assertSee('باب التسجيل مغلق حالياً ولا يمكن تقديم طلبات جديدة.', false)
            ->assertSee('انتهى التسجيل', false)
            ->assertDontSee('سجّل الدخول للتسجيل', false)
            ->assertDontSee('يجب تسجيل الدخول للتسجيل في البرنامج.', false)
            ->assertDontSee('سجّل في البرنامج', false);
    }

    public function test_staff_still_goes_to_admin_even_with_program_return(): void
    {
        Notification::fake();

        $this->makeOpenProgram('staff-return');
        $staff = User::factory()->create([
            'email' => 'staff-return@example.com',
            'password' => Hash::make('CorrectPass1!'),
            'role_type' => 'staff',
            'is_active' => true,
        ]);
        $staff->assignRole('staff');

        $this->get(route('login', ['return' => '/programs/staff-return']))
            ->assertSessionHas(SafeLoginReturnUrl::SESSION_KEY);

        $this->post(route('login'), [
            'email' => 'staff-return@example.com',
            'password' => 'CorrectPass1!',
        ])->assertRedirect(route('verification.notice'));

        $code = $this->latestOtpCode($staff);

        $this->post(route('verification.verify'), ['code' => $code])
            ->assertRedirect('/admin')
            ->assertSessionMissing(SafeLoginReturnUrl::SESSION_KEY);
    }

    public function test_unpublished_program_return_falls_back_to_portal(): void
    {
        Notification::fake();

        $program = $this->makeOpenProgram('will-unpublish');
        $user = $this->makeBeneficiary('unpub@example.com');

        $this->get(route('login', ['return' => '/programs/will-unpublish']))
            ->assertSessionHas(SafeLoginReturnUrl::SESSION_KEY, '/programs/will-unpublish');

        $program->update([
            'status' => ProgramStatus::Draft,
            'published_at' => null,
        ]);

        $this->post(route('login'), [
            'email' => 'unpub@example.com',
            'password' => 'CorrectPass1!',
        ])->assertRedirect(route('verification.notice'));

        $code = $this->latestOtpCode($user);

        $this->post(route('verification.verify'), ['code' => $code])
            ->assertRedirect(route('portal.dashboard'));
    }

    private function makeBeneficiary(string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make('CorrectPass1!'),
            'role_type' => 'beneficiary',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('beneficiary');
        Profile::query()->create(['user_id' => $user->id]);

        return $user;
    }

    private function makeOpenProgram(string $slug): TrainingProgram
    {
        return TrainingProgram::query()->create([
            'title' => 'برنامج '.$slug,
            'slug' => $slug,
            'status' => ProgramStatus::Published,
            'published_at' => now(),
            'learning_path_id' => null,
        ]);
    }

    private function latestOtpCode(User $user): string
    {
        $code = null;

        Notification::assertSentTo($user, VerifyEmailCode::class, function (VerifyEmailCode $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        });

        $this->assertNotNull($code);

        return (string) $code;
    }
}
