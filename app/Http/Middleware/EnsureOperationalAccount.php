<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureOperationalAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Guests are handled by auth middleware / Filament login; only gate signed-in users.
        if ($user === null) {
            return $next($request);
        }

        if (! $user->allowsOperationalAccess() || ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'لا يمكن الوصول إلى الحساب.']);
        }

        return $next($request);
    }
}
