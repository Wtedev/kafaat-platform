<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectToHttpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_insecure_request_is_redirected_when_force_https_enabled(): void
    {
        config(['security.force_https' => true]);

        $response = $this->get('http://localhost/login');

        $response->assertRedirect();
        $this->assertStringStartsWith('https://', $response->headers->get('Location') ?? '');
    }

    public function test_secure_request_is_not_redirected_when_force_https_enabled(): void
    {
        config(['security.force_https' => true]);

        $response = $this->get('https://localhost/login');

        $response->assertOk();
    }

    public function test_http_is_allowed_when_force_https_disabled(): void
    {
        config(['security.force_https' => false]);

        $response = $this->get('http://localhost/login');

        $response->assertOk();
    }
}
