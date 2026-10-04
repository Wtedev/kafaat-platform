<?php

namespace Tests\Feature;

use Database\Seeders\StaffUiPreviewSeeder;
use RuntimeException;
use Tests\TestCase;

class StaffUiPreviewSeederTest extends TestCase
{
    public function test_preview_seeder_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_ENV=local أو APP_ENV=staging');

        (new StaffUiPreviewSeeder)->run();
    }
}
