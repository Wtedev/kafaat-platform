<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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
            'name'       => ['required', 'string', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'city'       => ['nullable', 'string', 'max:100'],
            'bio'        => ['nullable', 'string', 'max:1000'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender'     => ['nullable', 'in:male,female'],
        ]);

        $user->update([
            'name'  => $validated['name'],
            'phone' => $validated['phone'],
        ]);

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'city'       => $validated['city'],
                'bio'        => $validated['bio'],
                'birth_date' => $validated['birth_date'],
                'gender'     => $validated['gender'],
            ]
        );

        return back()->with('success', 'تم حفظ الملف الشخصي بنجاح.');
    }
}
