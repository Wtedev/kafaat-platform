<?php

namespace App\Http\Middleware;

use App\Services\Operations\ErrorPageHitRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordErrorPageHit
{
    public function __construct(
        private readonly ErrorPageHitRecorder $recorder,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if ($this->shouldRecord($request, $response)) {
            $this->recorder->record($response->getStatusCode());
        }

        return $response;
    }

    private function shouldRecord(Request $request, Response $response): bool
    {
        if (! $this->recorder->shouldTrack($response->getStatusCode())) {
            return false;
        }

        // Health / probe traffic must never pollute visitor error stats.
        if ($request->is('up')) {
            return false;
        }

        // Count browser-style HTML error pages, not JSON/API or asset probes.
        if ($request->expectsJson()) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return false;
        }

        return true;
    }
}
