<?php

/**
 * Local-only screenshot login helpers.
 * Loaded only when APP_ENV=local and SCREENSHOT_TOKEN is set.
 */
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

if (! app()->environment('local')) {
    return;
}

$token = (string) env('SCREENSHOT_TOKEN', '');
if ($token === '') {
    return;
}

Route::get('/__local/screenshot-login/{kind}', function (string $kind) use ($token) {
    abort_unless(hash_equals($token, (string) request('token')), 403);

    $user = match ($kind) {
        'beneficiary' => User::query()->where('email', 'beneficiary.001@seed.kafaat.org.sa')->first(),
        'beneficiary-closed' => User::query()->where('email', 'beneficiary.002@seed.kafaat.org.sa')->first(),
        'admin' => User::query()->where('email', 'lama.almeshiqeh@kafaat.org.sa')->first()
            ?? User::query()->where('email', 'abdulsalam@kafaat.org.sa')->first()
            ?? User::query()->role('admin')->first(),
        default => null,
    };

    abort_unless($user instanceof User, 404);

    Auth::login($user, true);
    request()->session()->put('otp_verified', true);
    request()->session()->save();

    $redirect = match ($kind) {
        'admin' => '/admin/support-inbox',
        'beneficiary-closed' => '/portal',
        default => '/portal',
    };

    return redirect($redirect);
})->middleware('web')->name('local.screenshot-login');
