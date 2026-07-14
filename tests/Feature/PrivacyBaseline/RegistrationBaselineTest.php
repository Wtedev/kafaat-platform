<?php

namespace Tests\Feature\PrivacyBaseline;

use App\Enums\IdentityType;
use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\Concerns\SeedsActivePrivacyPolicy;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class RegistrationBaselineTest extends TestCase
{
    use GeneratesTestIdentityData;
    use RefreshDatabase;
    use SeedsActivePrivacyPolicy;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        $this->seedActivePrivacyPolicy();
    }

    public function test_register_page_is_accessible(): void
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_user_can_register_with_current_fields_and_profile_is_created(): void
    {
        Notification::fake();

        $payload = $this->validRegistrationPayload([
            'email' => 'new-beneficiary@example.com',
        ]);

        $response = $this->post(route('register'), $payload);

        $response->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'new-beneficiary@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('beneficiary', $user->role_type);
        $this->assertTrue($user->hasRole('beneficiary'));
        $this->assertTrue($user->is_active);
        $this->assertDatabaseHas('profiles', ['user_id' => $user->id]);
        $this->assertTrue(Hash::check('SecurePass1!', $user->password));
        $this->assertTrue($user->hasStructuredName());
        $this->assertTrue($user->hasIdentityOnRecord());

        Notification::assertSentTo($user, VerifyEmailCode::class);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $payload = $this->validRegistrationPayload(['email' => 'dup@example.com']);

        $response = $this->post(route('register'), $payload);

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::query()->where('email', 'dup@example.com')->count());
    }

    public function test_registration_validation_requires_core_fields(): void
    {
        $response = $this->post(route('register'), []);

        $response->assertSessionHasErrors([
            'first_name',
            'email',
            'password',
            'identity_number',
            'birth_date',
            'phone',
        ]);
    }
}
