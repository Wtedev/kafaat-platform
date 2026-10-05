<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\StaffUi\StaffUiAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffUiPreview
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $module = null): Response
    {
        $user = $request->user();

        if (StaffUiAccess::seesNewUi($user instanceof User ? $user : null) || StaffUiAccess::moduleIsReady($module)) {
            return $next($request);
        }

        abort(403);
    }
}
