<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

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
            'avatar' => ['nullable', 'image', 'max:2048'],
            'current_password' => ['required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $user->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
        ]);

        if (! empty($validated['password'])) {
            $user->update([
                'password' => $validated['password'],
            ]);
        }

        $profileData = [
            'city' => $validated['city'],
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
