<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\CompletePortalProfileRequest;
use App\Services\Identity\IdentityNumberService;
use App\Services\Identity\UserProfileCompletionService;
use App\Services\UserActivityLogger;
use App\Support\Auth\SafeLoginReturnUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class PortalProfileCompleteController extends Controller
{
    private const RETURN_SESSION_KEY = 'portal.profile_complete.return';

    public function __construct(
        private readonly UserProfileCompletionService $profileCompletionService,
    ) {}

    public function show(Request $request): View
    {
        $return = SafeLoginReturnUrl::sanitize($request->query(SafeLoginReturnUrl::QUERY_KEY));
        if ($return !== null) {
            $request->session()->put(self::RETURN_SESSION_KEY, $return);
        }

        $user = $request->user()->load('profile');

        return view('portal.profile-complete', compact('user'));
    }

    public function store(CompletePortalProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $this->profileCompletionService->complete($user, $request->validated());
        } catch (InvalidArgumentException $exception) {
            if ($exception->getMessage() === 'duplicate_identity') {
                return back()
                    ->withInput($request->except(['identity_number']))
                    ->withErrors([
                        'identity_number' => IdentityNumberService::DUPLICATE_MESSAGE,
                    ]);
            }

            if ($exception->getMessage() === 'identity_locked') {
                return back()->withErrors([
                    'identity_number' => 'لا يمكن تغيير رقم الهوية أو نوعها بعد تسجيلهما.',
                ]);
            }

            throw $exception;
        }

        UserActivityLogger::logProfileUpdated($user, ['استكمال بيانات الحساب']);

        $return = SafeLoginReturnUrl::sanitize($request->session()->pull(self::RETURN_SESSION_KEY));
        if ($return !== null) {
            return redirect()->to($return)
                ->with('success', 'تم حفظ بيانات حسابك بنجاح.');
        }

        return redirect()->route('portal.dashboard')
            ->with('success', 'تم حفظ بيانات حسابك بنجاح.');
    }
}
