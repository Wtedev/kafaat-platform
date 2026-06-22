<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationNoticeController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->redirectVerifiedUser($request);
        }

        return view('auth.verify-email');
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
