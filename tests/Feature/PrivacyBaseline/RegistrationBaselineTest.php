<?php

namespace Tests\Feature\PrivacyBaseline;

use App\Models\Profile;
use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class RegistrationBaselineTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
    }

    public function test_register_page_is_accessible(): void
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_user_can_register_with_current_fields_and_profile_is_created(): void
    {
        Notification::fake();

        $response = $this->post(route('register'), [
            'name' => 'أحمد المستفيد',
            'email' => 'new-beneficiary@example.com',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $response->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'new-beneficiary@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('beneficiary', $user->role_type);
        $this->assertTrue($user->hasRole('trainee'));
        $this->assertTrue($user->is_active);
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id]);
        $this->assertNotSame('SecurePass1!', $user->getRawOriginal('password'));
        $this->assertTrue(Hash::check('SecurePass1!', $user->password));

        Notification::assertSentTo($user, VerifyEmailCode::class);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $response = $this->post(route('register'), [
            'name' => 'مستخدم آخر',
            'email' => 'dup@example.com',
            'password' => 'SecurePass1!',
            'password_confirmation' => 'SecurePass1!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::query()->where('email', 'dup@example.com')->count());
    }

    public function test_registration_validation_requires_name_email_and_password_confirmation(): void
    {
        $response = $this->post(route('register'), []);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
    }
}
