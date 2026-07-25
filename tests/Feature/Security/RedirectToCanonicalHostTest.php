<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectToCanonicalHostTest extends TestCase
{
    use RefreshDatabase;

    public function test_railway_default_host_redirects_to_app_url(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.url' => 'https://kafaat.org.sa',
            'security.redirect_to_canonical_host' => true,
            'security.force_https' => false,
        ]);

        $response = $this->get('https://kafaat-platform-production.up.railway.app/login');

        $response->assertRedirect('https://kafaat.org.sa/login');
    }

    public function test_canonical_host_is_not_redirected(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.url' => 'https://kafaat.org.sa',
            'security.redirect_to_canonical_host' => true,
            'security.force_https' => false,
        ]);

        $response = $this->get('https://kafaat.org.sa/login');

        $response->assertOk();
        $this->assertFalse($response->isRedirection());
    }

    public function test_health_endpoint_is_not_redirected_on_railway_host(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.url' => 'https://kafaat.org.sa',
            'security.redirect_to_canonical_host' => true,
            'security.force_https' => false,
        ]);

        $response = $this->get('https://kafaat-platform-production.up.railway.app/up');

        $response->assertOk();
        $this->assertFalse($response->isRedirection());
    }

    public function test_local_environment_does_not_redirect_hosts(): void
    {
        $this->app['env'] = 'local';
        config([
            'app.url' => 'http://127.0.0.1:8000',
            'security.redirect_to_canonical_host' => true,
        ]);

        $response = $this->get('http://localhost/login');

        $response->assertOk();
        $this->assertFalse($response->isRedirection());
    }

    public function test_redirect_can_be_disabled(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.url' => 'https://kafaat.org.sa',
            'security.redirect_to_canonical_host' => false,
            'security.force_https' => false,
        ]);

        $response = $this->get('https://kafaat-platform-production.up.railway.app/login');

        $response->assertOk();
        $this->assertFalse($response->isRedirection());
    }
}
