<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RequestIdLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_id_is_shared_with_log_context(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $requestId = $response->headers->get('X-Request-ID');
        $this->assertNotEmpty($requestId);

        $context = Log::sharedContext();
        $this->assertSame($requestId, $context['request_id'] ?? null);
    }
}
