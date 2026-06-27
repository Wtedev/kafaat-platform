<?php

namespace Tests\Feature\TestIsolation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class SharedTestStateIsolationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    public function test_rate_limiter_state_does_not_leak_between_tests(): void
    {
        $key = md5('login127.0.0.1');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            RateLimiter::hit($key, 60);
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));
    }

    public function test_rate_limiter_starts_clean_after_previous_test(): void
    {
        $key = md5('login127.0.0.1');

        $this->assertFalse(RateLimiter::tooManyAttempts($key, 5));
    }

    public function test_config_changes_do_not_leak_between_tests(): void
    {
        Config::set('access_control.sensitive_reverify_ttl_seconds', 999);

        $this->assertSame(999, config('access_control.sensitive_reverify_ttl_seconds'));
    }

    public function test_config_is_restored_after_previous_test(): void
    {
        $this->assertNotSame(999, config('access_control.sensitive_reverify_ttl_seconds'));
    }

    public function test_authentication_does_not_leak_between_tests(): void
    {
        $user = User::factory()->create([
            'email' => 'auth-leak@example.com',
            'password' => Hash::make('SecretPass1!'),
        ]);

        $this->actingAs($user);

        $this->assertTrue(Auth::check());
    }

    public function test_next_test_starts_as_guest(): void
    {
        $this->assertFalse(Auth::check());
    }

    public function test_frozen_time_does_not_leak_between_tests(): void
    {
        Carbon::setTestNow('2020-01-01 12:00:00');

        $this->assertSame('2020-01-01 12:00:00', now()->toDateTimeString());
    }

    public function test_time_is_real_after_previous_test(): void
    {
        $this->assertNotSame('2020-01-01 12:00:00', now()->toDateTimeString());
    }

    public function test_permission_grants_do_not_leak_between_tests(): void
    {
        $this->seedRbacRoles();

        $user = User::factory()->create([
            'role_type' => 'trainee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('trainee');

        Permission::findOrCreate('isolation.test.permission', 'web');
        $user->givePermissionTo('isolation.test.permission');

        $this->assertTrue($user->fresh()->can('isolation.test.permission'));
    }

    public function test_fresh_user_does_not_inherit_previous_permissions(): void
    {
        $this->seedRbacRoles();

        $user = User::factory()->create([
            'role_type' => 'trainee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('trainee');

        $this->assertFalse($user->can('isolation.test.permission'));
    }
}
