<?php

namespace Tests\Feature;

use Database\Seeders\StaffUiPreviewSeeder;
use RuntimeException;
use Tests\TestCase;

class StaffUiPreviewSeederTest extends TestCase
{
    public function test_preview_seeder_refuses_to_run_outside_local(): void
    {
        $this->assertNotSame('local', app()->environment());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_ENV=local');

        (new StaffUiPreviewSeeder)->run();
    }
}
