<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetSharedTestState();
    }

    protected function tearDown(): void
    {
        $this->resetSharedTestState();

        Mockery::close();

        parent::tearDown();
    }

    /**
     * Clear process-local state that must not leak between test cases.
     *
     * The array cache driver and rate limiter counters persist for the life of the
     * PHPUnit process. Login throttling in particular uses the same IP for every
     * HTTP test and will eventually return 429, which surfaces as session/CSRF
     * failures in unrelated tests when the suite runs end-to-end.
     */
    protected function resetSharedTestState(): void
    {
        Carbon::setTestNow();

        if (! $this->app) {
            return;
        }

        Auth::logout();

        if ($this->app->bound(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $this->resetConfiguredRateLimiters();
    }

    protected function resetConfiguredRateLimiters(): void
    {
        if (! $this->app) {
            return;
        }

        $ip = '127.0.0.1';

        foreach (['login', 'register', 'forgot-password'] as $limiterName) {
            RateLimiter::clear(md5($limiterName.$ip));
        }
    }
}
