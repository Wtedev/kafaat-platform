<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\EmailVerificationCodeService;
use App\Enums\SecurityLogResult;
use App\Enums\SecurityLogSeverity;
use App\Services\Security\SecurityLogService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function __invoke(Request $request, EmailVerificationCodeService $service): RedirectResponse
    {
        $user = $request->user();

        if ($request->session()->get('otp_verified') === true) {
            return $this->redirectVerifiedUser($request);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ], [
            'code.required' => 'يرجى إدخال رمز التحقق.',
            'code.digits' => 'رمز التحقق يتكوّن من 6 أرقام.',
        ]);

        $result = $service->verify($user, $validated['code']);

        if ($result === 'success') {
            app(SecurityLogService::class)->record(
                'auth.otp_verified',
                SecurityLogResult::Success,
                SecurityLogSeverity::Info,
                $user,
                request: $request,
            );

            // فتح بوابة الجلسة لهذه الجلسة فقط.
            $request->session()->put('otp_verified', true);

            event(new Verified($user));

            return $this->redirectVerifiedUser($request)
                ->with('status', 'تم التحقق بنجاح.');
        }

        $severity = $result === 'too_many_attempts' ? SecurityLogSeverity::Warning : SecurityLogSeverity::Info;
        $event = match ($result) {
            'expired' => 'auth.otp_expired',
            'too_many_attempts' => 'auth.otp_locked',
            default => 'auth.otp_failed',
        };

        app(SecurityLogService::class)->record(
            $event,
            SecurityLogResult::Failed,
            $severity,
            $user,
            metadata: ['reason' => $result],
            request: $request,
        );

        $message = match ($result) {
            'expired' => 'انتهت صلاحية الرمز. اطلب رمزاً جديداً.',
            'too_many_attempts' => 'تجاوزت عدد المحاولات المسموح بها. اطلب رمزاً جديداً.',
            'not_found' => 'لا يوجد رمز فعّال. اطلب رمزاً جديداً.',
            default => 'رمز التحقق غير صحيح.',
        };

        return back()->withErrors(['code' => $message]);
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
