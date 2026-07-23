<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginRateLimitKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_rate_limit_is_scoped_per_email_and_ip(): void
    {
        User::factory()->create([
            'email' => 'limit-a@example.com',
            'password' => Hash::make('SecretPass1!'),
            'is_active' => true,
        ]);
        User::factory()->create([
            'email' => 'limit-b@example.com',
            'password' => Hash::make('SecretPass1!'),
            'is_active' => true,
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('login'))->post(route('login'), [
                'email' => 'limit-a@example.com',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->from(route('login'))->post(route('login'), [
            'email' => 'limit-a@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        // A different email from the same IP should still be allowed.
        $this->from(route('login'))->post(route('login'), [
            'email' => 'limit-b@example.com',
            'password' => 'SecretPass1!',
        ])->assertRedirect(route('verification.notice'));
    }
}
