<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\EmailVerificationCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationNoticeController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        // البوابة معتمدة على الجلسة: من أكمل الرمز في هذه الجلسة يُوجَّه لوجهته.
        if ($request->session()->get('otp_verified') === true) {
            return $this->redirectVerifiedUser($request);
        }

        $this->ensureActiveVerificationCode($request);

        return view('auth.verify-email');
    }

    private function ensureActiveVerificationCode(Request $request): void
    {
        $user = $request->user();

        $hasActiveCode = EmailVerificationCode::query()
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->exists();

        if ($hasActiveCode) {
            return;
        }

        $user->sendEmailVerificationNotification();

        session()->flash('status', 'أرسلنا رمز تحقق إلى بريدك الإلكتروني.');
    }

    private function redirectVerifiedUser(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isAdminOrStaff()) {
            return redirect('/admin');
        }

        return redirect()->route('portal.dashboard');
    }
}
