<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.']);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'حسابك غير مفعّل. تواصل مع الإدارة.']);
        }

        // Update last login timestamp
        $user->updateQuietly(['last_login_at' => now()]);

        // Role-based redirect
        if ($user->isAdminOrStaff()) {
            return redirect()->intended('/admin');
        }

        return redirect()->intended(route('portal.dashboard'));
    }
}
