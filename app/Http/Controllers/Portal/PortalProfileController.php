<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
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
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
        ]);

        $jobTitle = trim((string) ($validated['job_title'] ?? ''));
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

        return back()->with('success', 'تم حفظ الملف الشخصي بنجاح.');
    }
}
