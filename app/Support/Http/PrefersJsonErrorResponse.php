<?php

namespace App\Support\Http;

use Illuminate\Http\Request;

/**
 * Detect requests that must receive JSON errors instead of branded Blade pages.
 *
 * Livewire shows non-JSON error bodies in a full-screen HTML dialog. Our 5xx
 * pages auto-reload after 120 seconds — that looked like an admin "popup"
 * that vanished after ~two minutes (e.g. News image upload / save).
 */
final class PrefersJsonErrorResponse
{
    public static function matches(Request $request): bool
    {
        if ($request->expectsJson()) {
            return true;
        }

        if ($request->hasHeader('X-Livewire')) {
            return true;
        }

        if ($request->is('livewire/*', 'livewire-*')) {
            return true;
        }

        return false;
    }
}
