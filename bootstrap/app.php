<?php

use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\BeneficiaryPortal;
use App\Http\Middleware\EnsureAdminOrStaff;
use App\Http\Middleware\EnsureCurrentPrivacyPolicyAcknowledged;
use App\Http\Middleware\EnsureGateAttendanceAccess;
use App\Http\Middleware\EnsureOperationalAccount;
use App\Http\Middleware\EnsureOtpVerified;
use App\Http\Middleware\RecordErrorPageHit;
use App\Http\Middleware\RedirectToHttps;
use App\Services\Operations\ErrorPageVisitRecorder;
use App\Support\Http\PrefersJsonErrorResponse;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
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
        // Trust the platform reverse proxy so that:
        //   - $request->isSecure() returns true (X-Forwarded-Proto: https)
        //   - asset()/url()/route() generate https:// URLs
        //   - SESSION_SECURE_COOKIE works correctly
        //   - Livewire CSRF tokens round-trip correctly over HTTPS
        // Default TRUSTED_PROXIES=* is acceptable on Railway only while the
        // container is not directly reachable; override with CIDRs when known.
        // env() is intentional here — bootstrap runs before config is fully available.
        $trustedProxiesRaw = (string) env('TRUSTED_PROXIES', '*');
        $trustedProxies = $trustedProxiesRaw === '*' || $trustedProxiesRaw === ''
            ? '*'
            : array_values(array_filter(array_map('trim', explode(',', $trustedProxiesRaw))));
        $middleware->trustProxies(
            at: $trustedProxies === [] ? '*' : $trustedProxies,
            headers: Request::HEADER_X_FORWARDED_FOR |
                     Request::HEADER_X_FORWARDED_HOST |
                     Request::HEADER_X_FORWARDED_PORT |
                     Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->web(append: [
            AssignRequestId::class,
            RedirectToHttps::class,
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
            'operational' => EnsureOperationalAccount::class,
            'otp.verified' => EnsureOtpVerified::class,
            'privacy.acknowledged' => EnsureCurrentPrivacyPolicyAcknowledged::class,
            'gate.attendance' => EnsureGateAttendanceAccess::class,
        ]);

        // Run before Authenticate (interface + concrete) so non-operational sessions
        // are cleared instead of receiving Filament's 403.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: EnsureOperationalAccount::class,
        );

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
        // Prefer branded Blade error pages for normal browser navigations when APP_DEBUG=false.
        // Never return those HTML pages (with 120s auto-refresh) to Livewire/Filament AJAX —
        // Livewire embeds the body in an error dialog, which looked like a 2-minute "popup".
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e): bool {
            return PrefersJsonErrorResponse::matches($request);
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
            if (PrefersJsonErrorResponse::matches($request)) {
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
