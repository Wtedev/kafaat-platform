<?php

namespace App\Http\Controllers\Auth;

use App\Enums\SecurityLogResult;
use App\Enums\SecurityLogSeverity;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\PendingRegistrationService;
use App\Services\Identity\IdentityNumberService;
use App\Services\Security\SecurityLogService;
use App\Support\Auth\SafeLoginReturnUrl;
use App\Support\Privacy\SensitiveContactMasker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SignupVerificationController extends Controller
{
    public function __construct(
        private readonly PendingRegistrationService $pendingRegistrationService,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $pending = $this->pendingRegistrationService->findActiveForSession($request);

        if ($pending === null) {
            return redirect()->route('register')
                ->withErrors(['email' => 'انتهت جلسة إنشاء الحساب، يرجى البدء من جديد.']);
        }

        if ($pending->isExpired()) {
            return view('auth.register-verify', [
                'maskedEmail' => SensitiveContactMasker::maskEmail($pending->email),
                'signupStep' => 2,
                'canResend' => false,
                'resendCooldownSeconds' => 0,
                'expired' => true,
            ]);
        }

        $cooldownRemaining = 0;
        if (
            $pending->last_sent_at !== null
            && $pending->last_sent_at->gt(now()->subSeconds(PendingRegistrationService::RESEND_COOLDOWN_SECONDS))
        ) {
            $cooldownRemaining = (int) max(
                0,
                PendingRegistrationService::RESEND_COOLDOWN_SECONDS - $pending->last_sent_at->diffInSeconds(now()),
            );
        }

        return view('auth.register-verify', [
            'maskedEmail' => SensitiveContactMasker::maskEmail($pending->email),
            'signupStep' => 2,
            'canResend' => $cooldownRemaining === 0
                && $pending->resend_count < PendingRegistrationService::MAX_RESENDS,
            'resendCooldownSeconds' => $cooldownRemaining,
            'expired' => false,
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $pending = $this->pendingRegistrationService->findActiveForSession($request);

        if ($pending === null) {
            return redirect()->route('register')
                ->withErrors(['email' => 'انتهت جلسة إنشاء الحساب، يرجى البدء من جديد.']);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ], [
            'code.required' => 'يرجى إدخال رمز التحقق.',
            'code.digits' => 'رمز التحقق يتكوّن من 6 أرقام.',
        ]);

        $result = $this->pendingRegistrationService->verify($pending, $validated['code'], $request);

        if ($result instanceof User) {
            app(SecurityLogService::class)->record(
                'auth.signup_otp_verified',
                SecurityLogResult::Success,
                SecurityLogSeverity::Info,
                $result,
                request: $request,
            );

            return SafeLoginReturnUrl::redirectAfterVerification($request)
                ->with('success', 'تم التحقق من البريد وإنشاء حسابك بنجاح.')
                ->with('status', 'تم التحقق من البريد وإنشاء حسابك بنجاح.');
        }

        $severity = $result === 'too_many_attempts' ? SecurityLogSeverity::Warning : SecurityLogSeverity::Info;
        $event = match ($result) {
            'expired' => 'auth.signup_otp_expired',
            'too_many_attempts' => 'auth.signup_otp_locked',
            default => 'auth.signup_otp_failed',
        };

        app(SecurityLogService::class)->record(
            $event,
            SecurityLogResult::Failed,
            $severity,
            identifier: $pending->email,
            metadata: ['reason' => $result],
            request: $request,
        );

        if (in_array($result, ['email_in_use', 'create_failed', 'policy_unavailable', 'policy_stale', 'duplicate_identity'], true)) {
            $this->pendingRegistrationService->invalidateSessionPending($request);

            $message = match ($result) {
                'email_in_use' => 'يوجد حساب مرتبط بهذا البريد الإلكتروني، يمكنك تسجيل الدخول بدلًا من إنشاء حساب جديد.',
                'duplicate_identity' => IdentityNumberService::DUPLICATE_MESSAGE,
                default => 'تعذّر إنشاء الحساب، يرجى المحاولة مرة أخرى.',
            };

            return redirect()->route('register')
                ->withErrors(['email' => $message]);
        }

        $message = match ($result) {
            'expired' => 'انتهت صلاحية رمز التحقق، يرجى طلب رمز جديد.',
            'too_many_attempts' => 'تجاوزت عدد المحاولات المسموح بها. اطلب رمزاً جديداً.',
            'not_found' => 'انتهت جلسة إنشاء الحساب، يرجى البدء من جديد.',
            default => 'رمز التحقق غير صحيح.',
        };

        if ($result === 'not_found') {
            return redirect()->route('register')->withErrors(['email' => $message]);
        }

        return back()->withErrors(['code' => $message]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $pending = $this->pendingRegistrationService->findActiveForSession($request);

        if ($pending === null) {
            return redirect()->route('register')
                ->withErrors(['email' => 'انتهت جلسة إنشاء الحساب، يرجى البدء من جديد.']);
        }

        $result = $this->pendingRegistrationService->resend($pending);

        return match ($result) {
            'sent' => back()->with('status', 'تم إرسال رمز تحقق جديد إلى بريدك الإلكتروني.'),
            'cooldown' => back()->withErrors(['code' => 'يرجى الانتظار قبل طلب رمز جديد.']),
            'max_resends' => back()->withErrors(['code' => 'تم تجاوز الحد الأقصى لإعادة الإرسال. ابدأ من جديد أو حاول لاحقاً.']),
            'expired' => back()->withErrors(['code' => 'انتهت صلاحية رمز التحقق، يرجى طلب رمز جديد.']),
            default => redirect()->route('register')
                ->withErrors(['email' => 'انتهت جلسة إنشاء الحساب، يرجى البدء من جديد.']),
        };
    }
}
