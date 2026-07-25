<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep the browser on APP_URL's host in production.
 *
 * forceRootUrl(APP_URL) makes Vite/CSS absolute to the canonical domain. Visiting
 * the Railway default host (*.up.railway.app) while APP_URL is kafaat.org.sa then
 * loads cross-origin CSS; CSP style-src 'self' blocks it and the public site
 * renders unstyled (only inline-styled widgets like «لدي مشكلة» remain).
 */
class RedirectToCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment(['local', 'testing'])) {
            return $next($request);
        }

        if (! config('security.redirect_to_canonical_host', true)) {
            return $next($request);
        }

        // Platform health probes may hit the default Railway hostname.
        if ($request->is('up')) {
            return $next($request);
        }

        $canonical = rtrim((string) config('app.url'), '/');
        $canonicalHost = parse_url($canonical, PHP_URL_HOST);
        if (! is_string($canonicalHost) || $canonicalHost === '') {
            return $next($request);
        }

        $requestHost = strtolower($request->getHost());
        if ($requestHost === strtolower($canonicalHost)) {
            return $next($request);
        }

        $target = $canonical.$request->getRequestUri();

        return redirect()->to($target, 301);
    }
}
