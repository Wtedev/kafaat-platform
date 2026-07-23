<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AppUrlConfigurationTest extends TestCase
{
    public function test_generated_urls_use_configured_root_without_trailing_slash(): void
    {
        URL::forceRootUrl('https://kafaat.example.test');
        URL::forceScheme('https');

        $generated = url('/login');

        $this->assertSame('https://kafaat.example.test/login', $generated);
        $this->assertStringNotContainsString('example.test//', $generated);
    }

    public function test_app_url_config_trims_trailing_slash_at_bootstrap(): void
    {
        $this->assertFalse(str_ends_with((string) config('app.url'), '/'));
    }
}
