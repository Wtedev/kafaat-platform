<?php

namespace Tests\Unit\Support\Http;

use App\Support\Http\PrefersJsonErrorResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class PrefersJsonErrorResponseTest extends TestCase
{
    public function test_expects_json_is_true(): void
    {
        $request = Request::create('/x', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);

        $this->assertTrue(PrefersJsonErrorResponse::matches($request));
    }

    public function test_livewire_header_is_true(): void
    {
        $request = Request::create('/admin/news', 'POST');
        $request->headers->set('X-Livewire', 'true');

        $this->assertTrue(PrefersJsonErrorResponse::matches($request));
    }

    public function test_livewire_upload_path_is_true(): void
    {
        $request = Request::create('/livewire/upload-file', 'POST');

        $this->assertTrue(PrefersJsonErrorResponse::matches($request));
    }

    public function test_normal_browser_get_is_false(): void
    {
        $request = Request::create('/news/some-slug', 'GET');
        $request->headers->set('Accept', 'text/html');

        $this->assertFalse(PrefersJsonErrorResponse::matches($request));
    }
}
