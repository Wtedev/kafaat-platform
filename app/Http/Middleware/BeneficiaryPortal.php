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

        $user = $request->user();

        // الأدمن/الموظف يُوجَّهون للوحة الإدارة بدل 403 عند فتح روابط البوابة.
        if ($user->isAdminOrStaff()) {
            return redirect('/admin');
        }

        if (! $user->isPortalUser()) {
            abort(403, 'هذه الصفحة مخصصة للمستفيدين فقط.');
        }

        if (! $user->allowsOperationalAccess()) {
            abort(403, 'لا يمكن الوصول إلى البوابة بهذا الحساب.');
        }

        return $next($request);
    }
}
