<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    public function show(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token'                 => ['required'],
            'email'                 => ['required', 'email'],
            'password'              => ['required', 'min:8', 'confirmed'],
        ], [
            'token.required'              => 'رمز إعادة التعيين مطلوب.',
            'email.required'              => 'البريد الإلكتروني مطلوب.',
            'email.email'                 => 'صيغة البريد الإلكتروني غير صحيحة.',
            'password.required'           => 'كلمة المرور مطلوبة.',
            'password.min'                => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.',
            'password.confirmed'          => 'تأكيد كلمة المرور غير متطابق.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('status', 'تم تغيير كلمة المرور بنجاح. يمكنك تسجيل الدخول الآن.');
        }

        return back()->withErrors(['email' => 'رابط إعادة التعيين غير صالح أو منتهي الصلاحية.']);
    }
}
