<?php

namespace Tests\Feature\PrivacyPhase02;

use App\Enums\IdentityType;
use App\Models\User;
use App\Notifications\VerifyEmailCode;
use App\Services\Identity\IdentityNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\GeneratesTestIdentityData;
use Tests\Concerns\SeedsActivePrivacyPolicy;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class RegistrationIdentityTest extends TestCase
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

    public function test_registration_creates_structured_user_profile_and_encrypted_identity(): void
    {
        Notification::fake();

        $payload = $this->validRegistrationPayload();
        $response = $this->post(route('register'), $payload);

        $response->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', $payload['email'])->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('beneficiary'));
        $this->assertSame('أحمد محمد عبدالله السعود', $user->fullName());
        $this->assertSame($user->fullName(), $user->name);
        $this->assertTrue(Hash::check('SecurePass1!', $user->password));
        $this->assertSame('+966501234567', $user->phone);
        $this->assertSame('1995-05-15', $user->profile?->birth_date?->toDateString());
        $this->assertSame('male', $user->profile?->gender?->value);

        $this->assertNotNull($user->identity_number_ciphertext);
        $this->assertNotNull($user->identity_number_lookup_hash);
        $this->assertSame(substr($payload['identity_number'], -4), $user->identity_number_last4);

        Notification::assertSentTo($user, VerifyEmailCode::class);
    }

    public function test_duplicate_identity_is_rejected_with_arabic_message(): void
    {
        $identity = $this->generateValidNationalId();

        $firstUser = User::factory()->create([
            'email' => 'first@example.com',
            'role_type' => 'trainee',
        ]);

        $payload = IdentityNumberService::prepareStoragePayload($identity, IdentityType::NationalId);
        $firstUser->forceFill([
            'identity_type' => $payload['identity_type']->value,
            'identity_number_ciphertext' => $payload['identity_number_ciphertext'],
            'identity_number_lookup_hash' => $payload['identity_number_lookup_hash'],
            'identity_number_last4' => $payload['identity_number_last4'],
            'identity_confirmed_at' => $payload['identity_confirmed_at'],
        ])->save();

        Notification::fake();

        $second = $this->validRegistrationPayload([
            'identity_number' => $identity,
            'email' => 'second@example.com',
        ]);

        $this->post(route('register'), $second)
            ->assertSessionHasErrors('identity_number')
            ->assertSessionHasErrors(['identity_number' => IdentityNumberService::DUPLICATE_MESSAGE]);

        $this->assertFalse(User::query()->where('email', 'second@example.com')->exists());
    }

    public function test_registration_accepts_ten_digit_identity_without_checksum(): void
    {
        Notification::fake();

        $payload = $this->validRegistrationPayload([
            'identity_number' => '1234567890',
        ]);

        $this->post(route('register'), $payload)
            ->assertRedirect(route('verification.notice'));

        $this->assertTrue(User::query()->where('email', $payload['email'])->exists());
    }

    public function test_registration_rejects_identity_not_exactly_ten_digits(): void
    {
        Notification::fake();

        $payload = $this->validRegistrationPayload([
            'identity_number' => '123456789',
        ]);

        $this->post(route('register'), $payload)
            ->assertSessionHasErrors(['identity_number' => 'رقم الهوية أو الإقامة يجب أن يكون 10 أرقام.']);

        $this->assertSame(0, User::query()->where('email', $payload['email'])->count());
    }

    public function test_registration_rolls_back_on_failure(): void
    {
        Notification::fake();

        $payload = $this->validRegistrationPayload([
            'identity_number' => 'invalid',
        ]);

        $this->post(route('register'), $payload)->assertSessionHasErrors('identity_number');
        $this->assertSame(0, User::query()->where('email', $payload['email'])->count());
    }
}
