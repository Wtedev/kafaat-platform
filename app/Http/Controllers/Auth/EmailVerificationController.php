<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class EmailVerificationController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->redirectVerifiedUser($request);
        }

        $request->fulfill();

        event(new Verified($request->user()));

        return $this->redirectVerifiedUser($request)
            ->with('status', 'تم التحقق من بريدك الإلكتروني بنجاح.');
    }

    private function redirectVerifiedUser(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isAdminOrStaff()) {
            return redirect('/admin');
        }

        return redirect()->route('portal.dashboard');
    }
}
