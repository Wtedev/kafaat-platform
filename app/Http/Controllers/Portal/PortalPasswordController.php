<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\UpdatePortalPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PortalPasswordController extends Controller
{
    public function show(Request $request): View
    {
        return view('portal.settings.password', [
            'user' => $request->user(),
        ]);
    }

    public function update(UpdatePortalPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($request->validated('password')),
        ])->save();

        return redirect()
            ->route('portal.settings.password')
            ->with('success', 'تم تحديث كلمة المرور بنجاح.');
    }
}
