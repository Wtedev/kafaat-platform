<?php

use App\Http\Middleware\BeneficiaryPortal;
use App\Http\Middleware\EnsureAdminOrStaff;
use App\Http\Middleware\EnsureCurrentPrivacyPolicyAcknowledged;
use App\Http\Middleware\EnsureGateAttendanceAccess;
use App\Http\Middleware\EnsureOtpVerified;
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

        $middleware->web(append: [
            \App\Http\Middleware\AssignRequestId::class,
            \App\Http\Middleware\ApplySecurityHeaders::class,
        ]);

        $trustedHosts = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_HOSTS', '')),
        )));
        if ($trustedHosts !== []) {
            $middleware->trustHosts(at: $trustedHosts);
        }

        $middleware->alias([
            'beneficiary' => BeneficiaryPortal::class,
            'admin-or-staff' => EnsureAdminOrStaff::class,
            'otp.verified' => EnsureOtpVerified::class,
            'privacy.acknowledged' => EnsureCurrentPrivacyPolicyAcknowledged::class,
            'gate.attendance' => EnsureGateAttendanceAccess::class,
        ]);

        // Redirect authenticated users away from guest-only pages
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(function (Request $request) {
            $user = $request->user();

            // البوابة معتمدة على الجلسة: يلزم إدخال رمز التحقق في كل دخول.
            if ($user && $request->session()->get('otp_verified') !== true) {
                return route('verification.notice');
            }

            if ($user && $user->isAdminOrStaff()) {
                return '/admin';
            }

            return route('portal.dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Prefer branded Blade error pages for browser requests when APP_DEBUG=false.
        // Filament/Livewire keep their own in-panel error UI for component failures;
        // these views only cover HTTP status responses rendered by Laravel.
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e): bool {
            return $request->expectsJson();
        });
    })->create();
