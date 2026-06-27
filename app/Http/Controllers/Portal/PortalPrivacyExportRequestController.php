<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Privacy\PrivacyRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PortalPrivacyExportRequestController extends Controller
{
    public function __construct(
        private readonly PrivacyRequestService $privacyRequestService,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        try {
            $privacyRequest = $this->privacyRequestService->submitDataExport(
                $user,
                $request,
                $validated['password'],
            );
        } catch (\InvalidArgumentException) {
            return redirect()
                ->route('portal.privacy')
                ->with('error', 'لا يمكن تقديم طلب تصدير جديد حالياً. راجع الطلب أو الملف الحالي.');
        }

        return redirect()
            ->route('portal.privacy')
            ->with('success', 'تم تقديم طلب تصدير بياناتك. المرجع: '.$privacyRequest->uuid);
    }
}
