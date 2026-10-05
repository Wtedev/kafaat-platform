<?php

namespace Tests\Feature\Filament;

use App\Enums\AccountStatus;
use App\Filament\Pages\StaffProfilePage;
use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleComponents\Checksum;
use PHPUnit\Framework\Attributes\DataProvider;
use Predis\Client;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

/**
 * Livewire component updates are POSTed to /livewire/update, which only runs the
 * `web` group plus middleware explicitly registered as Livewire-persistent. These
 * tests drive that endpoint over real HTTP so session authentication is exercised
 * the same way a browser exercises it.
 */
class LivewireSessionAuthenticationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbacRoles;

    private const ORIGINAL_NAME = 'الاسم الأصلي';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function sessionDriverProvider(): array
    {
        return [
            'database' => ['database'],
            'file' => ['file'],
            'array' => ['array'],
            'redis' => ['redis'],
        ];
    }

    #[DataProvider('sessionDriverProvider')]
    public function test_valid_session_can_still_drive_livewire_updates(string $driver): void
    {
        $this->skipUnsupportedDriver($driver);
        $this->useSessionDriver($driver);

        $staff = $this->makeStaff();
        $sessionId = $this->loginRealSession($staff);

        $this->openProfilePage($sessionId);

        $response = $this->callSave($sessionId, 'اسم محدث');

        $response->assertOk();
        $this->assertSame('اسم محدث', $staff->fresh()->name);
    }

    #[DataProvider('sessionDriverProvider')]
    public function test_session_invalidated_elsewhere_cannot_drive_livewire_updates(string $driver): void
    {
        $this->skipUnsupportedDriver($driver);
        $this->useSessionDriver($driver);

        $staff = $this->makeStaff();
        $sessionId = $this->loginRealSession($staff);

        $this->openProfilePage($sessionId);

        $this->changePasswordOnAnotherDevice($staff);

        $response = $this->callSave($sessionId, 'اسم بعد الإبطال');

        $response->assertUnauthorized();
        $this->assertSame(self::ORIGINAL_NAME, $staff->fresh()->name);
    }

    /**
     * The password is rotated from a different browser, which leaves the password hash
     * stored in this session stale. A real request would load the user fresh from the
     * database, so the guard's cached instance is refreshed to match.
     */
    private function changePasswordOnAnotherDevice(User $user): void
    {
        DB::table('users')
            ->where('id', $user->id)
            ->update(['password' => Hash::make('RotatedElsewhere1!')]);

        auth()->guard('web')->user()?->refresh();
    }

    private function skipUnsupportedDriver(string $driver): void
    {
        if ($driver !== 'redis') {
            return;
        }

        if (! extension_loaded('redis') && ! class_exists(Client::class)) {
            $this->markTestSkipped('Redis session driver needs the phpredis extension or predis/predis, neither is installed.');
        }

        try {
            $this->app['redis']->connection(config('session.connection') ?? 'default')->ping();
        } catch (\Throwable $exception) {
            $this->markTestSkipped('No reachable Redis server: '.$exception->getMessage());
        }
    }

    private function useSessionDriver(string $driver): void
    {
        config(['session.driver' => $driver]);

        $this->app->singleton('session', fn ($app) => new SessionManager($app));
        $this->app->singleton('session.store', fn ($app) => $app->make('session')->driver());
    }

    /**
     * Establish a genuinely authenticated, persisted session and return its id.
     */
    private function loginRealSession(User $user): string
    {
        $this->startSession();

        auth()->guard('web')->login($user);
        session()->put('otp_verified', true);
        session()->save();

        return (string) session()->getId();
    }

    private function openProfilePage(string $sessionId): void
    {
        $this->withCookie((string) config('session.cookie'), $sessionId)
            ->get(StaffProfilePage::getUrl())
            ->assertOk();
    }

    /**
     * Call StaffProfilePage::save() through the real Livewire update endpoint.
     */
    private function callSave(string $sessionId, string $name): TestResponse
    {
        $snapshot = $this->profileSnapshot($sessionId);
        $token = (string) session()->token();

        // Each Livewire update arrives in a fresh container in production, so the
        // per-request caches Livewire keeps are empty; reset them here as well or the
        // middleware pipeline is skipped as already-applied.
        Livewire::flushState();

        return $this->withCookie((string) config('session.cookie'), $sessionId)
            ->withHeaders(['X-Livewire' => 'true'])
            ->postJson(app('livewire')->getUpdateUri(), [
                '_token' => $token,
                'components' => [
                    [
                        'snapshot' => json_encode($snapshot),
                        'updates' => ['data.name' => $name],
                        'calls' => [
                            ['path' => '', 'method' => 'save', 'params' => []],
                        ],
                    ],
                ],
            ]);
    }

    /**
     * Build the snapshot a browser would hold after rendering /admin/profile. The
     * test harness renders on its own endpoint, so the path is rewritten (and the
     * checksum recomputed) to point at the panel route.
     *
     * @return array<string, mixed>
     */
    private function profileSnapshot(string $sessionId): array
    {
        $this->withCookie((string) config('session.cookie'), $sessionId);
        $snapshot = Livewire::test(StaffProfilePage::class)->snapshot;

        $snapshot['memo']['path'] = ltrim(StaffProfilePage::getUrl(isAbsolute: false), '/');
        unset($snapshot['checksum']);
        $snapshot['checksum'] = Checksum::generate($snapshot);

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeStaff(array $overrides = []): User
    {
        $staff = User::factory()->create(array_merge([
            'name' => self::ORIGINAL_NAME,
            'role_type' => 'staff',
            'is_active' => true,
            'account_status' => AccountStatus::Active,
            'email_verified_at' => now(),
        ], $overrides));
        $staff->assignRole(RbacCatalog::ROLE_STAFF);

        return $staff->fresh();
    }
}
