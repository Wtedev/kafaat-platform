<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\UserActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PortalProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('profile');

        return view('portal.profile', compact('user'));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:160'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        $changedFields = [];

        if ($user->name !== $validated['name']) {
            $changedFields[] = 'الاسم';
        }

        if (($user->phone ?? '') !== ($validated['phone'] ?? '')) {
            $changedFields[] = 'الجوال';
        }

        $profile = $user->profile;
        $jobTitle = trim((string) ($validated['job_title'] ?? ''));

        if (($profile?->city ?? '') !== ($validated['city'] ?? '')) {
            $changedFields[] = 'المدينة';
        }

        if (($profile?->job_title ?? '') !== ($jobTitle !== '' ? $jobTitle : null)) {
            $changedFields[] = 'المسمى الوظيفي';
        }

        if ($request->hasFile('avatar')) {
            $changedFields[] = 'الصورة الشخصية';
        }

        $user->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
        ]);

        $profileData = [
            'city' => $validated['city'],
            'job_title' => $jobTitle !== '' ? $jobTitle : null,
        ];

        if ($request->hasFile('avatar')) {
            $existing = $user->profile?->avatar;
            if ($existing && Storage::disk('public')->exists($existing)) {
                Storage::disk('public')->delete($existing);
            }
            $profileData['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $profileData,
        );

        UserActivityLogger::logProfileUpdated($user, $changedFields);

        return back()->with('success', 'تم حفظ الملف الشخصي بنجاح.');
    }
}
