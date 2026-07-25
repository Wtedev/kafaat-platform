<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Privacy\PrivacyRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PortalAccountDeletionController extends Controller
{
    public function __construct(
        private readonly PrivacyRequestService $privacyRequestService,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'كلمة المرور غير صحيحة.',
            ]);
        }

        $privacyRequest = $this->privacyRequestService->submitAccountDeletion(
            $user,
            $validated['reason'] ?? null,
            $request,
        );

        $this->privacyRequestService->verifyIdentityWithPassword(
            $privacyRequest,
            $user,
            $validated['password'],
            $request,
        );

        return redirect()
            ->route('portal.settings')
            ->with('success', 'تم تقديم طلب حذف حسابك. سيراجعه فريق الخصوصية قبل أي تنفيذ.');
    }
}
