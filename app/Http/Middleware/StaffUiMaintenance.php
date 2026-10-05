<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffUiMaintenance
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $module = null): Response
    {
        if (! config('staff_ui.maintenance')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user instanceof User && $this->bypasses($user)) {
            return $next($request);
        }

        $module ??= $request->route()?->defaults['staff_ui_module'] ?? null;
        $ready = config('staff_ui.ready_modules', []);

        if (is_string($module) && $module !== '' && is_array($ready) && in_array($module, $ready, true)) {
            return $next($request);
        }

        return response()
            ->view('staff.maintenance')
            ->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE);
    }

    private function bypasses(User $user): bool
    {
        return $user->isAdmin() || $user->hasRole('super_admin');
    }
}
