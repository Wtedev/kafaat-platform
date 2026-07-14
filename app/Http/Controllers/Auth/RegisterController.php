<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\UserRegistrationService;
use App\Services\Identity\IdentityNumberService;
use App\Services\Privacy\PrivacyPolicyAcknowledgementService;
use App\Services\Privacy\PrivacyPolicyService;
use App\Services\UserActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use InvalidArgumentException;

class RegisterController extends Controller
{
    public function __construct(
        private readonly UserRegistrationService $registrationService,
        private readonly PrivacyPolicyAcknowledgementService $acknowledgementService,
    ) {}

    public function show(): View
    {
        $policy = PrivacyPolicyService::active();

        if ($policy === null) {
            Log::warning('privacy_policy.unavailable', ['route' => 'register']);

            return view('auth.register-unavailable');
        }

        return view('auth.register', [
            'privacyPolicy' => $policy,
            'acknowledgementText' => $this->acknowledgementService->acknowledgementText(),
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
            $user = $this->registrationService->register(
                $request->validated(),
                $policy,
                $request,
            );
        } catch (InvalidArgumentException $exception) {
            if ($exception->getMessage() === 'duplicate_identity') {
                return back()
                    ->withInput($request->except(['password', 'password_confirmation', 'identity_number']))
                    ->withErrors([
                        'identity_number' => IdentityNumberService::DUPLICATE_MESSAGE,
                    ]);
            }

            throw $exception;
        }

        UserActivityLogger::logAccountCreated($user);

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('verification.notice')
            ->with('status', 'أرسلنا رمز تحقق إلى بريدك الإلكتروني. يرجى إدخاله لتفعيل حسابك.');
    }
}
