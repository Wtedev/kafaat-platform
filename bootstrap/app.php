<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust Railway's reverse proxy so that:
        //   - $request->isSecure() returns true (X-Forwarded-Proto: https)
        //   - asset()/url()/route() generate https:// URLs
        //   - SESSION_SECURE_COOKIE works correctly
        //   - Livewire CSRF tokens round-trip correctly over HTTPS
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
                     Request::HEADER_X_FORWARDED_HOST |
                     Request::HEADER_X_FORWARDED_PORT |
                     Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->alias([
            'beneficiary'    => \App\Http\Middleware\BeneficiaryPortal::class,
            'admin-or-staff' => \App\Http\Middleware\EnsureAdminOrStaff::class,
        ]);

        // Redirect authenticated users away from guest-only pages
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(function (Request $request) {
            $user = $request->user();
            if ($user && $user->isAdminOrStaff()) {
                return '/admin';
            }
            return route('portal.dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
