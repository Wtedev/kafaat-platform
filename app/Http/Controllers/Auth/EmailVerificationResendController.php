<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\EmailVerificationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class EmailVerificationResendController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->session()->get('otp_verified') === true) {
            return redirect()->route('portal.dashboard');
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (Throwable) {
            $request->session()->put(EmailVerificationCodeService::SEND_FAILED_SESSION_KEY, true);

            return back();
        }

        return back()->with('status', 'تم إرسال رمز تحقق جديد إلى بريدك الإلكتروني.');
    }
}
