<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_include_security_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy');
        $response->assertHeader('X-Frame-Options');
        $response->assertHeader('Permissions-Policy');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $response->assertHeader('X-Request-ID');
    }

    public function test_hsts_not_sent_on_http_local_requests(): void
    {
        $response = $this->get(route('home'));

        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_csp_blocks_the_vite_dev_server_outside_local(): void
    {
        $response = $this->get(route('home'));

        $response->assertHeader('Content-Security-Policy');
        $this->assertStringNotContainsString('5173', (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_local_vite_dev_server_is_allowed_by_csp_while_hot_file_exists(): void
    {
        $hot = public_path('hot');
        $existed = is_file($hot);
        $original = $existed ? (string) file_get_contents($hot) : null;
        file_put_contents($hot, "http://127.0.0.1:5173\n");
        $this->app['env'] = 'local';

        try {
            $csp = (string) $this->get(route('home'))->headers->get('Content-Security-Policy');
        } finally {
            if ($existed) {
                file_put_contents($hot, $original);
            } else {
                @unlink($hot);
            }
        }

        $this->assertStringContainsString(
            "style-src 'self' 'unsafe-inline' https://unpkg.com http://127.0.0.1:5173",
            $csp,
        );
        $this->assertStringContainsString('connect-src \'self\' https://*.basemaps.cartocdn.com http://127.0.0.1:5173 ws://127.0.0.1:5173', $csp);
    }
}
