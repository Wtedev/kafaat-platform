<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\UpdatePortalProfileRequest;
use App\Services\Identity\IdentityNumberService;
use App\Services\Identity\UserProfileCompletionService;
use App\Services\Media\PublicMediaLifecycleService;
use App\Services\UserActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class PortalProfileController extends Controller
{
    public function __construct(
        private readonly UserProfileCompletionService $profileCompletionService,
        private readonly PublicMediaLifecycleService $publicMedia,
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

        $profile = $user->profile()->firstOrCreate(['user_id' => $user->id]);
        $previousAvatar = $profile->avatar;

        if ($request->boolean('remove_avatar') && ! $request->hasFile('avatar')) {
            $profile->update(['avatar' => null]);
            $this->publicMedia->deleteOwnedPath($previousAvatar);
        } elseif ($request->hasFile('avatar')) {
            $storedPath = null;

            try {
                $storedPath = $this->publicMedia->storeUpload($request->file('avatar'), 'avatars');
                $profile->update(['avatar' => $storedPath]);
                $this->publicMedia->deleteOwnedIfReplaced($previousAvatar, $storedPath);
            } catch (Throwable $e) {
                $this->publicMedia->discardFailedUpload($storedPath);

                throw $e;
            }
        }

        UserActivityLogger::logProfileUpdated($user, ['الملف الشخصي']);

        return redirect()
            ->route('portal.settings.profile')
            ->with('success', 'تم حفظ الملف الشخصي بنجاح.');
    }
}
