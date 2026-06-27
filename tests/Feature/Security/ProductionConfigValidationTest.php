<?php

namespace Tests\Feature\Security;

use App\Services\Operations\ProductionEnvironmentValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionConfigValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_environment_has_no_production_violations(): void
    {
        $this->assertSame([], app(ProductionEnvironmentValidator::class)->violations());
    }

    public function test_production_debug_is_flagged(): void
    {
        config(['app.env' => 'production', 'app.debug' => true]);

        $violations = app(ProductionEnvironmentValidator::class)->violations();

        $this->assertContains('APP_DEBUG must be false in production.', $violations);
    }
}
