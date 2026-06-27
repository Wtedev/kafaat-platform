<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BeneficiaryPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (! $request->user()->isPortalUser()) {
            abort(403, 'هذه الصفحة مخصصة للمستفيدين فقط.');
        }

        if (! $request->user()->allowsOperationalAccess()) {
            abort(403, 'لا يمكن الوصول إلى البوابة بهذا الحساب.');
        }

        return $next($request);
    }
}
