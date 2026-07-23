<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class CacheConfigurationTest extends TestCase
{
    public function test_cache_disallows_class_serialization(): void
    {
        $this->assertFalse(config('cache.serializable_classes'));
    }

    public function test_cache_prefix_is_configured(): void
    {
        $this->assertNotSame('', (string) config('cache.prefix'));
    }
}
