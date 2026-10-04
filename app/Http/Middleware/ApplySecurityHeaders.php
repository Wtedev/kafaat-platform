<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', (string) config('security.headers.referrer_policy'));
        $response->headers->set('Permissions-Policy', (string) config('security.headers.permissions_policy'));
        $response->headers->set('X-Frame-Options', (string) config('security.headers.frame_options'));

        if ($request->routeIs('gate.*')) {
            $response->headers->set('Referrer-Policy', 'no-referrer');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            $response->headers->set(
                'Permissions-Policy',
                'camera=(self), microphone=(), geolocation=(), payment=(), usb=()',
            );
        }

        $coop = (string) config('security.headers.cross_origin_opener_policy', '');
        if ($coop !== '') {
            $response->headers->set('Cross-Origin-Opener-Policy', $coop);
        }

        $corp = (string) config('security.headers.cross_origin_resource_policy', '');
        if ($corp !== '') {
            $response->headers->set('Cross-Origin-Resource-Policy', $corp);
        }

        $csp = $this->contentSecurityPolicy();
        if ($csp !== '') {
            $header = config('security.headers.content_security_policy_report_only', false)
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';
            $response->headers->set($header, $csp);
        }

        if ($request->isSecure() && config('security.hsts.enabled', false)) {
            $maxAge = (int) config('security.hsts.max_age', 31536000);
            $directive = 'max-age='.$maxAge;
            if (config('security.hsts.include_subdomains', false)) {
                $directive .= '; includeSubDomains';
            }
            if (config('security.hsts.preload', false)) {
                $directive .= '; preload';
            }
            $response->headers->set('Strict-Transport-Security', $directive);
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $csp = (string) config('security.headers.content_security_policy');
        $vite = $this->localViteOrigin();
        if ($csp === '' || $vite === null) {
            return $csp;
        }

        $ws = preg_replace('#^http:#', 'ws:', $vite) ?? $vite;

        foreach ([
            'style-src' => $vite,
            'script-src' => $vite,
            'font-src' => $vite,
            'img-src' => $vite,
            'connect-src' => $vite.' '.$ws,
        ] as $directive => $source) {
            $csp = $this->appendCspSource($csp, $directive, $source);
        }

        return $csp;
    }

    private function localViteOrigin(): ?string
    {
        if (! $this->isLocal() || ! $this->isRunningHot()) {
            return null;
        }

        $hot = public_path('hot');

        $parts = parse_url(trim((string) file_get_contents($hot)));
        if (! is_array($parts) || ! isset($parts['host']) || $parts['host'] === '') {
            return null;
        }

        $origin = ($parts['scheme'] ?? 'http').'://'.$parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }

    private function isLocal(): bool
    {
        return app()->environment('local');
    }

    private function isRunningHot(): bool
    {
        return app(Vite::class)->isRunningHot();
    }

    private function appendCspSource(string $csp, string $directive, string $source): string
    {
        $pattern = '/(?:^|; )'.preg_quote($directive, '/').' [^;]*/';
        $count = 0;
        $updated = preg_replace_callback($pattern, function (array $match) use ($source): string {
            if (str_contains($match[0], $source)) {
                return $match[0];
            }

            return $match[0].' '.$source;
        }, $csp, 1, $count);

        if ($count > 0) {
            return $updated ?? $csp;
        }

        return rtrim($csp, '; ').'; '.$directive.' '.$source;
    }
}
