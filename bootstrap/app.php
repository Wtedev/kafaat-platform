<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'beneficiary'    => \App\Http\Middleware\BeneficiaryPortal::class,
            'admin-or-staff' => \App\Http\Middleware\EnsureAdminOrStaff::class,
        ]);

        // Redirect authenticated users away from guest-only pages
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(function (\Illuminate\Http\Request $request) {
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
