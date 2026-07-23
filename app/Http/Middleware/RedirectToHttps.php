<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense-in-depth HTTP→HTTPS redirect when FORCE_HTTPS / production defaults apply.
 * Prefer trusting X-Forwarded-Proto via trusted proxies; this catches plain HTTP
 * that still reaches the application process.
 */
class RedirectToHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.force_https', false)) {
            return $next($request);
        }

        if ($request->isSecure()) {
            return $next($request);
        }

        $httpsUrl = $request->getUri();
        $httpsUrl = preg_replace('#^http://#i', 'https://', $httpsUrl, 1) ?? $httpsUrl;

        return redirect()->to($httpsUrl, 301);
    }
}
