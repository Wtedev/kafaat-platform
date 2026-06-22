<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يفرض إدخال رمز التحقق (OTP) في كل جلسة دخول لجميع أنواع الحسابات
 * (مستفيد، موظف، أدمن). البوابة معتمدة على الجلسة وليست على email_verified_at،
 * حتى يُطلب الرمز في كل تسجيل دخول وليس مرة واحدة فقط.
 */
class EnsureOtpVerified
{
    public function handle(Request $request, Closure $next, string $redirectRoute = 'verification.notice'): Response
    {
        $user = $request->user();

        if ($user !== null && $request->session()->get('otp_verified') !== true) {
            if ($request->expectsJson()) {
                abort(403, 'يلزم التحقق من رمز البريد الإلكتروني.');
            }

            return redirect()->route($redirectRoute);
        }

        return $next($request);
    }
}
