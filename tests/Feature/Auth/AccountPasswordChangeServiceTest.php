<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\AccountPasswordChangeService;
use App\Services\Rbac\RbacCatalog;
use Filament\Facades\Filament;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class AccountPasswordChangeServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_change_with_array_session_driver_does_not_touch_sessions_table(): void
    {
        config(['session.driver' => 'array']);

        $user = $this->makeStaff();
        $oldRemember = $user->remember_token;

        app(AccountPasswordChangeService::class)->change(
            $user,
            'password',
            'NewPassword1!',
            session()->getId(),
        );

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword1!', $user->password));
        $this->assertNotSame($oldRemember, $user->remember_token);
        $this->assertSame(0, DB::table('sessions')->count());
    }

    public function test_change_with_database_sessions_keeps_current_and_invalidates_other(): void
    {
        $this->useDatabaseSessions();

        $user = $this->makeStaff();
        $oldRemember = $user->remember_token;

        $this->actingAs($user);
        $currentSessionId = (string) session()->getId();
        session()->save();

        $otherSessionId = str_repeat('b', 40);
        $this->insertDatabaseSession($otherSessionId, $user, 'other-browser');

        app(AccountPasswordChangeService::class)->change(
            $user,
            'password',
            'NewPassword1!',
            $currentSessionId,
        );

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword1!', $user->password));
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertNotSame($oldRemember, $user->remember_token);

        $this->assertDatabaseHas('sessions', ['id' => $currentSessionId, 'user_id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['id' => $otherSessionId]);

        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->app['auth']->forgetGuards();

        $this->withCookie(config('session.cookie'), $otherSessionId)
            ->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_failed_change_does_not_log_password_values(): void
    {
        $user = $this->makeStaff();

        try {
            app(AccountPasswordChangeService::class)->change(
                $user,
                'wrong-current',
                'NewPassword1!',
                session()->getId(),
            );
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $encoded = json_encode($exception->errors(), JSON_UNESCAPED_UNICODE);
            $this->assertIsString($encoded);
            $this->assertStringNotContainsString('wrong-current', $encoded);
            $this->assertStringNotContainsString('NewPassword1!', $encoded);
        }
    }

    private function makeStaff(): User
    {
        $staff = User::factory()->create([
            'role_type' => 'staff',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff->assignRole(RbacCatalog::ROLE_STAFF);

        return $staff->fresh();
    }

    private function insertDatabaseSession(string $sessionId, User $user, string $userAgent = 'test'): void
    {
        $loginKey = 'login_web_'.sha1(SessionGuard::class);

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => $userAgent,
            'payload' => base64_encode($loginKey.'|i:'.$user->id.';'),
            'last_activity' => now()->timestamp,
        ]);
    }

    private function useDatabaseSessions(): void
    {
        config(['session.driver' => 'database']);

        $this->app->singleton('session', fn ($app) => new SessionManager($app));
        $this->app->singleton('session.store', fn ($app) => $app->make('session')->driver());
    }
}
