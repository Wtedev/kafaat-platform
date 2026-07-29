<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\PendingRegistrationService;
use App\Services\Identity\IdentityNumberService;
use App\Services\Privacy\PrivacyPolicyAcknowledgementService;
use App\Services\Privacy\PrivacyPolicyService;
use App\Support\Auth\SafeLoginReturnUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class RegisterController extends Controller
{
    public function __construct(
        private readonly PendingRegistrationService $pendingRegistrationService,
        private readonly PrivacyPolicyAcknowledgementService $acknowledgementService,
    ) {}

    public function show(Request $request): View
    {
        SafeLoginReturnUrl::captureFromRequest($request);

        if ($request->boolean('restart')) {
            $this->pendingRegistrationService->invalidateSessionPending($request);
        }

        $policy = PrivacyPolicyService::active();

        if ($policy === null) {
            Log::warning('privacy_policy.unavailable', ['route' => 'register']);

            return view('auth.register-unavailable');
        }

        return view('auth.register', [
            'privacyPolicy' => $policy,
            'acknowledgementText' => $this->acknowledgementService->acknowledgementText(),
            'signupStep' => 1,
        ]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $policy = PrivacyPolicyService::active();

        if ($policy === null) {
            Log::warning('privacy_policy.unavailable', ['route' => 'register.store']);

            return back()
                ->withInput($request->except(['password', 'password_confirmation', 'identity_number']))
                ->withErrors([
                    'privacy_policy_acknowledged' => 'التسجيل غير متاح مؤقتاً، يرجى المحاولة لاحقاً.',
                ]);
        }

        try {
            $this->pendingRegistrationService->start($request->validated(), $request);
        } catch (InvalidArgumentException $exception) {
            if ($exception->getMessage() === 'email_in_use') {
                return back()
                    ->withInput($request->except(['password', 'password_confirmation', 'identity_number']))
                    ->withErrors([
                        'email' => 'يوجد حساب مرتبط بهذا البريد الإلكتروني، يمكنك تسجيل الدخول بدلًا من إنشاء حساب جديد.',
                    ]);
            }

            if ($exception->getMessage() === 'duplicate_identity') {
                return back()
                    ->withInput($request->except(['password', 'password_confirmation', 'identity_number']))
                    ->withErrors([
                        'identity_number' => IdentityNumberService::DUPLICATE_MESSAGE,
                    ]);
            }

            throw $exception;
        } catch (Throwable $exception) {
            Log::error('signup.start.failed', [
                'exception' => $exception::class,
            ]);

            return back()
                ->withInput($request->except(['password', 'password_confirmation', 'identity_number']))
                ->withErrors([
                    'email' => 'تعذّر إنشاء الحساب، يرجى المحاولة مرة أخرى.',
                ]);
        }

        return redirect()->route('register.verify.show')
            ->with('status', 'أرسلنا رمز تحقق إلى بريدك الإلكتروني. لن يتم إنشاء حسابك حتى يتم التحقق من بريدك الإلكتروني.');
    }
}
