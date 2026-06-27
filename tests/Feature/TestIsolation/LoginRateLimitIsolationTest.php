<?php

namespace Tests\Feature\TestIsolation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginRateLimitIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_login_attempts_do_not_block_later_login_tests(): void
    {
        User::factory()->create([
            'email' => 'rate-limit-a@example.com',
            'password' => Hash::make('SecretPass1!'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login'), [
                'email' => 'rate-limit-a@example.com',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login'), [
            'email' => 'rate-limit-a@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    public function test_valid_login_still_works_after_previous_test_exhausted_limiter(): void
    {
        User::factory()->create([
            'email' => 'rate-limit-b@example.com',
            'password' => Hash::make('SecretPass1!'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->post(route('login'), [
            'email' => 'rate-limit-b@example.com',
            'password' => 'SecretPass1!',
        ])->assertRedirect(route('verification.notice'));
    }
}
