<?php

namespace App\Http\Middleware;

use App\Services\Operations\ErrorPageVisitRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RecordErrorPageHit
{
    public function __construct(
        private readonly ErrorPageVisitRecorder $recorder,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        try {
            $exception = $request->attributes->get(ErrorPageVisitRecorder::EXCEPTION_ATTRIBUTE);
            $this->recorder->recordFromResponse(
                $request,
                $response,
                $exception instanceof Throwable ? $exception : null,
            );
        } catch (Throwable) {
            // Never break the response over metrics.
        }

        return $response;
    }
}
