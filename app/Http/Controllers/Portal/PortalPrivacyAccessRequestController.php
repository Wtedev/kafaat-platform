<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Privacy\PrivacyRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PortalPrivacyAccessRequestController extends Controller
{
    public function __construct(
        private readonly PrivacyRequestService $privacyRequestService,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $privacyRequest = $this->privacyRequestService->submitDataAccess($user, $request);
        } catch (\InvalidArgumentException $exception) {
            return redirect()
                ->route('portal.privacy')
                ->with('error', 'لديك بالفعل طلب وصول نشط.');
        }

        return redirect()
            ->route('portal.privacy')
            ->with('success', 'تم تقديم طلب الوصول إلى بياناتك. المرجع: '.$privacyRequest->uuid);
    }
}
