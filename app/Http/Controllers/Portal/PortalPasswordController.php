<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\UpdatePortalPasswordRequest;
use App\Services\Auth\AccountPasswordChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalPasswordController extends Controller
{
    public function __construct(
        private readonly AccountPasswordChangeService $passwordChangeService,
    ) {}

    public function show(Request $request): View
    {
        return view('portal.settings.password', [
            'user' => $request->user(),
        ]);
    }

    public function update(UpdatePortalPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        $this->passwordChangeService->change(
            $user,
            (string) $request->validated('current_password'),
            (string) $request->validated('password'),
            $request->session()->getId(),
        );

        return redirect()
            ->route('portal.settings.password')
            ->with('success', AccountPasswordChangeService::MSG_SUCCESS);
    }
}
