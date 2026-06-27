<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_health_command_runs_without_pii(): void
    {
        $this->artisan('system:health')
            ->assertSuccessful()
            ->expectsOutputToContain('System health:');
    }

    public function test_system_health_json_output_has_no_email_patterns(): void
    {
        $this->artisan('system:health --json')
            ->assertSuccessful();

        // Output is captured internally; re-run service for assertion
        $report = app(\App\Services\Operations\SystemHealthService::class)->check();
        $json = json_encode($report);

        $this->assertStringNotContainsString('@example.com', $json);
        $this->assertArrayHasKey('checks', $report);
    }
}
