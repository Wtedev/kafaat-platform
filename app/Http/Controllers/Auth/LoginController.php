<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Enums\SecurityLogResult;
use App\Enums\SecurityLogSeverity;
use App\Services\Security\SecurityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            app(SecurityLogService::class)->record(
                'auth.login_failed',
                SecurityLogResult::Failed,
                SecurityLogSeverity::Warning,
                identifier: (string) $credentials['email'],
                metadata: ['reason' => 'invalid_credentials'],
                request: $request,
            );

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.']);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if (! $user->account_status?->allowsLogin()) {
            app(SecurityLogService::class)->record(
                'auth.login_blocked',
                SecurityLogResult::Blocked,
                SecurityLogSeverity::Warning,
                $user,
                metadata: ['reason' => 'account_status_'.$user->account_status?->value],
                request: $request,
            );

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'لا يمكن تسجيل الدخول إلى هذا الحساب.']);
        }

        if (! $user->is_active) {
            app(SecurityLogService::class)->record(
                'auth.login_blocked',
                SecurityLogResult::Blocked,
                SecurityLogSeverity::Warning,
                $user,
                metadata: ['reason' => 'inactive_account'],
                request: $request,
            );

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'حسابك غير مفعّل. تواصل مع الإدارة.']);
        }

        // Update last login timestamp
        $user->updateQuietly(['last_login_at' => now()]);

        // Clear any url.intended that Filament may have stored pointing to /admin.
        // Using redirect()->intended() here would allow a poisoned session (from a
        // prior unauthenticated visit to /admin) to redirect a beneficiary → 403.
        $request->session()->forget('url.intended');

        // رمز التحقق إلزامي في كل دخول لجميع الأنواع (مستفيد/موظف/أدمن).
        // الـ Login listener أرسل الرمز وضبط بوابة الجلسة otp_verified=false.
        return redirect()->route('verification.notice')
            ->with('status', 'أرسلنا رمز تحقق إلى بريدك الإلكتروني. يرجى إدخاله للمتابعة.');
    }
}
