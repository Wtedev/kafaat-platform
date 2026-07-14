<?php

use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\BeneficiaryPortal;
use App\Http\Middleware\EnsureAdminOrStaff;
use App\Http\Middleware\EnsureCurrentPrivacyPolicyAcknowledged;
use App\Http\Middleware\EnsureGateAttendanceAccess;
use App\Http\Middleware\EnsureOtpVerified;
use App\Http\Middleware\RecordErrorPageHit;
use App\Services\Operations\ErrorPageVisitRecorder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
            AssignRequestId::class,
            ApplySecurityHeaders::class,
        ]);

        // Global so unmatched routes (404) and Filament stacks still get counted.
        $middleware->append(RecordErrorPageHit::class);

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
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e): bool {
            return $request->expectsJson();
        });

        // Attach the throwable so RecordErrorPageHit can store exception_class once.
        $exceptions->reportable(function (Throwable $e): void {
            try {
                $request = request();
                if ($request) {
                    $request->attributes->set(
                        ErrorPageVisitRecorder::EXCEPTION_ATTRIBUTE,
                        $e,
                    );
                }
            } catch (Throwable) {
                // Ignore — metrics must never affect reporting.
            }
        });

        // Fallback branded Arabic pages for uncommon 4xx/5xx without a dedicated view.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            $status = $e->getStatusCode();
            if (view()->exists('errors.'.$status)) {
                return null;
            }

            if ($status >= 500 && view()->exists('errors.5xx')) {
                return response()->view('errors.5xx', [
                    'exception' => $e,
                    'status' => $status,
                ], $status);
            }

            if ($status >= 400 && view()->exists('errors.4xx')) {
                return response()->view('errors.4xx', [
                    'exception' => $e,
                    'status' => $status,
                ], $status);
            }

            return null;
        });

        // Single recording path for responses that went through the exception renderer
        // (complements middleware; once-per-request flag prevents double inserts).
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            try {
                app(ErrorPageVisitRecorder::class)
                    ->recordFromResponse($request, $response, $e);
            } catch (Throwable) {
                // Never alter the original error response.
            }

            return $response;
        });
    })->create();
