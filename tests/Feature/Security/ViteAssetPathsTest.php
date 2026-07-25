<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class ViteAssetPathsTest extends TestCase
{
    public function test_vite_asset_paths_are_root_relative(): void
    {
        $path = Vite::asset('resources/css/app.css');

        $this->assertStringStartsWith('/build/', $path);
        $this->assertStringEndsWith('.css', $path);
        $this->assertStringNotContainsString('://', $path);
    }
}
