<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\CompletePortalProfileRequest;
use App\Services\Identity\UserProfileCompletionService;
use App\Services\UserActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class PortalProfileCompleteController extends Controller
{
    public function __construct(
        private readonly UserProfileCompletionService $profileCompletionService,
    ) {}

    public function show(Request $request): View
    {
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
                        'identity_number' => 'تعذر حفظ رقم الهوية بهذه البيانات. تواصل مع الدعم إذا كنت تعتقد أن هذا خطأ.',
                    ]);
            }

            throw $exception;
        }

        UserActivityLogger::logProfileUpdated($user, ['استكمال بيانات الحساب']);

        return redirect()->route('portal.dashboard')
            ->with('success', 'تم حفظ بيانات حسابك بنجاح.');
    }
}
