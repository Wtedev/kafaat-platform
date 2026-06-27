<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SessionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_regenerates_session(): void
    {
        User::factory()->create([
            'email' => 'session-regen@example.com',
            'password' => Hash::make('SecretPass1!'),
        ]);

        $this->get(route('login'));
        $before = session()->getId();

        $this->post(route('login'), [
            'email' => 'session-regen@example.com',
            'password' => 'SecretPass1!',
        ])->assertRedirect(route('verification.notice'));

        $this->assertNotSame($before, session()->getId());
    }
}
