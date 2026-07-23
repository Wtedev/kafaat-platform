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

    public function test_production_requires_https_url_and_force_https(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'app.url' => 'http://example.test',
            'security.force_https' => false,
            'security.trusted_hosts' => ['example.test'],
            'session.secure' => true,
            'session.http_only' => true,
            'session.encrypt' => true,
            'session.same_site' => 'lax',
            'session.driver' => 'database',
            'queue.default' => 'database',
            'cache.default' => 'database',
            'logging.channels.stack.channels' => ['stderr'],
            'mail.default' => 'resend',
        ]);

        $violations = app(ProductionEnvironmentValidator::class)->violations();

        $this->assertContains('APP_URL must use HTTPS in production.', $violations);
        $this->assertContains('FORCE_HTTPS must be enabled in production.', $violations);
    }

    public function test_production_flags_trailing_slash_on_app_url(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'app.url' => 'https://example.test/',
            'security.force_https' => true,
            'security.trusted_hosts' => ['example.test'],
            'session.secure' => true,
            'session.http_only' => true,
            'session.encrypt' => true,
            'session.same_site' => 'lax',
            'session.driver' => 'database',
            'queue.default' => 'database',
            'cache.default' => 'database',
            'logging.channels.stack.channels' => ['stderr'],
            'mail.default' => 'resend',
        ]);

        $violations = app(ProductionEnvironmentValidator::class)->violations();

        $this->assertContains('APP_URL must not end with a trailing slash.', $violations);
    }

    public function test_production_flags_insecure_cache_and_logging_defaults(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'app.url' => 'https://example.test',
            'security.force_https' => true,
            'security.trusted_hosts' => ['example.test'],
            'session.secure' => true,
            'session.http_only' => true,
            'session.encrypt' => true,
            'session.same_site' => 'lax',
            'session.driver' => 'database',
            'queue.default' => 'database',
            'cache.default' => 'file',
            'logging.channels.stack.channels' => ['single'],
            'mail.default' => 'resend',
        ]);

        $violations = app(ProductionEnvironmentValidator::class)->violations();

        $this->assertContains('CACHE_STORE cannot be file in production; use database or redis.', $violations);
        $this->assertContains('LOG_STACK must include stderr in production for platform log aggregation.', $violations);
    }
}
