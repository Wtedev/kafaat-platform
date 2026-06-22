<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminOrStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (! $request->user()->isAdminOrStaff()) {
            abort(403, 'هذه الصفحة مخصصة للمسؤولين والموظفين فقط.');
        }

        return $next($request);
    }
}
