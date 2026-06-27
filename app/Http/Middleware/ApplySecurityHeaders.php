<?php

namespace App\Http\Middleware;

use Closure;
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

        $csp = (string) config('security.headers.content_security_policy');
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
}
