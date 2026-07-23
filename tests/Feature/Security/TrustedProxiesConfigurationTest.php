<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class TrustedProxiesConfigurationTest extends TestCase
{
    public function test_security_config_exposes_trusted_proxies_default(): void
    {
        $this->assertSame('*', config('security.trusted_proxies'));
    }
}
