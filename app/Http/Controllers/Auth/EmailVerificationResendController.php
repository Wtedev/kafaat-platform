<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationResendController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('portal.dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'تم إعادة إرسال رابط التحقق إلى بريدك الإلكتروني.');
    }
}
