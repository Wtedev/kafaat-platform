<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\CancelEmailChangeRequest;
use App\Http\Requests\Portal\ResendEmailChangeCodeRequest;
use App\Http\Requests\Portal\StartEmailChangeRequest;
use App\Http\Requests\Portal\VerifyEmailChangeRequest;
use App\Services\Auth\EmailChangeService;
use Illuminate\Http\RedirectResponse;

class PortalEmailChangeController extends Controller
{
    public function __construct(
        private readonly EmailChangeService $emailChangeService,
    ) {}

    public function start(StartEmailChangeRequest $request): RedirectResponse
    {
        $user = $request->user();
        $result = $this->emailChangeService->start(
            $user,
            (string) $request->validated('email'),
            (string) $request->validated('email_confirmation'),
        );

        if (! $result['ok']) {
            $field = $result['field'] ?? 'email';

            return back()
                ->withInput($request->only('email', 'email_confirmation'))
                ->withErrors([$field => $result['message']])
                ->with('email_change_open', true);
        }

        return redirect()
            ->route('portal.settings.account')
            ->with('email_change_step', 'otp')
            ->with('status', 'تم إرسال رمز التحقق إلى بريدك الجديد.');
    }

    public function resend(ResendEmailChangeCodeRequest $request): RedirectResponse
    {
        $result = $this->emailChangeService->resend($request->user());

        if (! $result['ok']) {
            return redirect()
                ->route('portal.settings.account')
                ->with('email_change_step', 'otp')
                ->withErrors(['code' => $result['message']]);
        }

        return redirect()
            ->route('portal.settings.account')
            ->with('email_change_step', 'otp')
            ->with('status', 'تم إرسال رمز تحقق جديد إلى بريدك الجديد.');
    }

    public function verify(VerifyEmailChangeRequest $request): RedirectResponse
    {
        $result = $this->emailChangeService->verify(
            $request->user(),
            (string) $request->validated('code'),
        );

        if (! $result['ok']) {
            $keepOtpStep = $result['message'] !== EmailChangeService::MSG_EXPIRED_OTP
                && $result['message'] !== EmailChangeService::MSG_TOO_MANY_ATTEMPTS
                && $result['message'] !== EmailChangeService::MSG_NO_PENDING
                && $result['message'] !== EmailChangeService::MSG_IN_USE
                && $result['message'] !== EmailChangeService::MSG_ACCOUNT_BLOCKED;

            $redirect = redirect()->route('portal.settings.account')
                ->withErrors(['code' => $result['message']]);

            if ($keepOtpStep) {
                $redirect->with('email_change_step', 'otp');
            }

            return $redirect;
        }

        return redirect()
            ->route('portal.settings.account')
            ->with('success', EmailChangeService::MSG_SUCCESS);
    }

    public function cancel(CancelEmailChangeRequest $request): RedirectResponse
    {
        $this->emailChangeService->cancel($request->user());

        return redirect()
            ->route('portal.settings.account')
            ->with('status', 'تم إلغاء طلب تغيير البريد الإلكتروني.');
    }
}
