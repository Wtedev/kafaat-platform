<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PrivacyRequest;
use App\Services\Privacy\PrivacyRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PortalPrivacyRequestCancelController extends Controller
{
    public function __construct(
        private readonly PrivacyRequestService $privacyRequestService,
    ) {}

    public function store(Request $request, PrivacyRequest $privacyRequest): RedirectResponse
    {
        if ($privacyRequest->user_id !== $request->user()->id) {
            abort(403);
        }

        $this->privacyRequestService->cancel($privacyRequest, $request->user());

        return redirect()
            ->route('portal.privacy')
            ->with('success', 'تم إلغاء الطلب.');
    }
}
