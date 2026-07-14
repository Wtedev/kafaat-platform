<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\CompletePortalProfileRequest;
use App\Http\Requests\Portal\UpdatePortalProfileRequest;
use App\Services\Identity\IdentityNumberService;
use App\Services\Identity\UserProfileCompletionService;
use App\Services\UserActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class PortalProfileController extends Controller
{
    public function __construct(
        private readonly UserProfileCompletionService $profileCompletionService,
    ) {}

    public function show(Request $request): RedirectResponse
    {
        return redirect()->route('portal.settings.profile');
    }

    public function update(UpdatePortalProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $validated = $request->validated();
            $this->profileCompletionService->updateProfile($user, $validated);
        } catch (InvalidArgumentException $exception) {
            if ($exception->getMessage() === 'duplicate_identity') {
                return back()
                    ->withInput($request->except(['identity_number']))
                    ->withErrors([
                        'identity_number' => IdentityNumberService::DUPLICATE_MESSAGE,
                    ]);
            }

            throw $exception;
        }

        if ($request->hasFile('avatar')) {
            $existing = $user->profile?->avatar;
            if ($existing && Storage::disk('public')->exists($existing)) {
                Storage::disk('public')->delete($existing);
            }

            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                ['avatar' => $request->file('avatar')->store('avatars', 'public')],
            );
        }

        UserActivityLogger::logProfileUpdated($user, ['الملف الشخصي']);

        return redirect()
            ->route('portal.settings.profile')
            ->with('success', 'تم حفظ الملف الشخصي بنجاح.');
    }
}
