<?php

namespace App\Http\Middleware;

use App\Services\Privacy\PrivacyPolicyAcknowledgementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentPrivacyPolicyAcknowledged
{
    public function __construct(
        private readonly PrivacyPolicyAcknowledgementService $acknowledgementService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->acknowledgementService->userNeedsAcknowledgement($user)) {
            return $next($request);
        }

        if ($request->routeIs(
            'logout',
            'public.privacy',
            'public.privacy.version',
            'portal.privacy-policy.acknowledge',
            'portal.privacy-policy.acknowledge.store',
        )) {
            return $next($request);
        }

        if ($request->routeIs('portal.*') && $request->routeIs('portal.privacy-policy.acknowledge*')) {
            return $next($request);
        }

        return redirect()->route('portal.privacy-policy.acknowledge');
    }
}
