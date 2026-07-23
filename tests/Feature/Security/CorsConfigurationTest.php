<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class CorsConfigurationTest extends TestCase
{
    public function test_cors_defaults_are_same_origin_restrictive(): void
    {
        $this->assertSame([], config('cors.allowed_origins'));
        $this->assertFalse((bool) config('cors.supports_credentials'));
        $this->assertContains('api/*', config('cors.paths'));
        $this->assertNotContains('*', config('cors.allowed_methods'));
    }
}
