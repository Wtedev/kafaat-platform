<?php

namespace Tests\Unit\Deployment;

use Tests\TestCase;

class WebRuntimeScriptTest extends TestCase
{
    public function test_web_script_defaults_to_frankenphp_and_rolls_back_with_one_variable(): void
    {
        $script = (string) file_get_contents(base_path('railway/run-web.sh'));

        $this->assertStringContainsString('WEB_RUNTIME:-frankenphp', $script);
        $this->assertStringContainsString('php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"', $script);
        $this->assertStringContainsString('frankenphp run --config /app/Caddyfile', $script);
        $this->assertStringContainsString('php artisan storage:link', $script);
        $this->assertStringContainsString('php artisan optimize', $script);
        $this->assertStringContainsString('NEVER rm -rf storage/app/public', $script);
    }

    public function test_caddyfile_enables_production_opcache_and_long_static_cache(): void
    {
        $caddy = (string) file_get_contents(base_path('Caddyfile'));

        $this->assertStringContainsString('num_threads {$WEB_THREADS:32}', $caddy);
        $this->assertStringContainsString('opcache.validate_timestamps 0', $caddy);
        $this->assertStringContainsString('opcache.memory_consumption 256', $caddy);
        $this->assertStringContainsString('opcache.max_accelerated_files 20000', $caddy);
        $this->assertStringContainsString('path /build/*', $caddy);
        $this->assertStringContainsString('path /fonts/* /images/*', $caddy);
        $this->assertStringContainsString('max-age=31536000, immutable', $caddy);
        $this->assertStringContainsString('max-age=2592000', $caddy);
    }
}
