<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role_type' => 'beneficiary',
                'is_active' => true,
            ]);

            $user->assignRole('trainee');

            $user->profile()->create();

            return $user;
        });

        // Auth::login يُطلق حدث Login الذي يرسل رمز OTP ويضبط بوابة الجلسة.
        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('verification.notice')
            ->with('status', 'أرسلنا رمز تحقق إلى بريدك الإلكتروني. يرجى إدخاله لتفعيل حسابك.');
    }
}
