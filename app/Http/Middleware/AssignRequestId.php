<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);
        $request->attributes->set('request_id', $requestId);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $header = (string) config('access_control.trusted_request_id_header', 'X-Request-ID');
        $candidate = $request->headers->get($header);

        if ($request->isFromTrustedProxy()
            && is_string($candidate)
            && $candidate !== ''
            && Str::isUuid($candidate)) {
            return $candidate;
        }

        return (string) Str::uuid();
    }
}
