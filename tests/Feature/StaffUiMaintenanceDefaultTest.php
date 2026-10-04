<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Services\Rbac\RbacCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Tests\Concerns\ActsAsOtpVerifiedUser;
use Tests\Concerns\SeedsRbacRoles;
use Tests\TestCase;

class StaffUiMaintenanceDefaultTest extends TestCase
{
    use ActsAsOtpVerifiedUser;
    use RefreshDatabase;
    use SeedsRbacRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbacRoles();
    }

    public function test_staff_are_not_blocked_when_the_maintenance_env_is_absent(): void
    {
        $previous = $this->dropMaintenanceEnv();

        try {
            $loaded = require base_path('config/staff_ui.php');
            $this->assertFalse($loaded['maintenance']);
            config(['staff_ui.maintenance' => $loaded['maintenance']]);

            $staff = User::factory()->create([
                'role_type' => 'staff',
                'is_active' => true,
                'account_status' => AccountStatus::Active,
                'email_verified_at' => now(),
            ]);
            $staff->assignRole(RbacCatalog::ROLE_STAFF);

            $this->actingAsOtpVerified($staff)
                ->get('/admin')
                ->assertOk()
                ->assertDontSee('واجهة الموظفين قيد العمل حالياً');
        } finally {
            $this->restoreMaintenanceEnv($previous);
        }
    }

    /**
     * @return array{env:?string, server:?string, getenv:?string}
     */
    private function dropMaintenanceEnv(): array
    {
        $previous = [
            'env' => $_ENV['STAFF_UI_MAINTENANCE'] ?? null,
            'server' => $_SERVER['STAFF_UI_MAINTENANCE'] ?? null,
            'getenv' => getenv('STAFF_UI_MAINTENANCE') === false ? null : getenv('STAFF_UI_MAINTENANCE'),
        ];

        unset($_ENV['STAFF_UI_MAINTENANCE'], $_SERVER['STAFF_UI_MAINTENANCE']);
        putenv('STAFF_UI_MAINTENANCE');
        Env::getRepository()->clear('STAFF_UI_MAINTENANCE');
        $this->assertFalse(Env::getRepository()->has('STAFF_UI_MAINTENANCE'));

        return $previous;
    }

    /**
     * @param  array{env:?string, server:?string, getenv:?string}  $previous
     */
    private function restoreMaintenanceEnv(array $previous): void
    {
        if ($previous['env'] !== null) {
            $_ENV['STAFF_UI_MAINTENANCE'] = $previous['env'];
        } else {
            unset($_ENV['STAFF_UI_MAINTENANCE']);
        }

        if ($previous['server'] !== null) {
            $_SERVER['STAFF_UI_MAINTENANCE'] = $previous['server'];
        } else {
            unset($_SERVER['STAFF_UI_MAINTENANCE']);
        }

        if ($previous['getenv'] !== null) {
            putenv('STAFF_UI_MAINTENANCE='.$previous['getenv']);
        }
    }
}
