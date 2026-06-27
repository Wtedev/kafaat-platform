<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\UserRegistrationService;
use App\Services\UserActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class RegisterController extends Controller
{
    public function __construct(
        private readonly UserRegistrationService $registrationService,
    ) {}

    public function show(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        try {
            $user = $this->registrationService->register($request->validated());
        } catch (InvalidArgumentException $exception) {
            if ($exception->getMessage() === 'duplicate_identity') {
                return back()
                    ->withInput($request->except(['password', 'password_confirmation', 'identity_number']))
                    ->withErrors([
                        'identity_number' => 'تعذر إكمال التسجيل بهذه البيانات. يمكنك استخدام استعادة الحساب أو التواصل مع الدعم.',
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
