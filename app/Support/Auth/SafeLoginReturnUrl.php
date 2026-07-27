<?php

namespace App\Support\Auth;

use App\Enums\CompetencyTrack;
use App\Enums\ProgramStatus;
use App\Models\TrainingProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Stores and resolves a safe post-login return URL for public program pages.
 *
 * Uses a dedicated session key (not Laravel's url.intended) so Filament /admin
 * guest redirects cannot poison beneficiary destinations.
 */
final class SafeLoginReturnUrl
{
    public const SESSION_KEY = 'login.return_url';

    public const QUERY_KEY = 'return';

    public static function captureFromRequest(Request $request): void
    {
        $candidate = $request->query(self::QUERY_KEY);

        if (! is_string($candidate) || $candidate === '') {
            return;
        }

        $safe = self::sanitize($candidate);

        if ($safe !== null) {
            $request->session()->put(self::SESSION_KEY, $safe);
        }
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public static function redirectAfterVerification(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && $user->isAdminOrStaff()) {
            self::forget($request);

            return redirect('/admin');
        }

        $return = self::pull($request);

        if ($return !== null) {
            return redirect()->to($return);
        }

        return redirect()->route('portal.dashboard');
    }

    public static function pull(Request $request): ?string
    {
        $stored = $request->session()->pull(self::SESSION_KEY);

        if (! is_string($stored)) {
            return null;
        }

        return self::sanitize($stored);
    }

    /**
     * Accept only same-origin public program show URLs (path + query).
     * Rejects external hosts, /admin, and unpublished/missing programs.
     */
    public static function sanitize(?string $candidate): ?string
    {
        if (! is_string($candidate)) {
            return null;
        }

        $candidate = trim($candidate);

        if ($candidate === '') {
            return null;
        }

        if (str_contains($candidate, "\0")
            || str_contains($candidate, "\r")
            || str_contains($candidate, "\n")
            || str_contains($candidate, '\\')
            || str_contains($candidate, '@')
        ) {
            return null;
        }

        if (preg_match('#^https?://#i', $candidate) === 1 || str_starts_with($candidate, '//')) {
            $appUrl = rtrim((string) config('app.url'), '/');
            $normalized = rtrim($candidate, '/');

            if ($appUrl === '' || ! str_starts_with(strtolower($candidate), strtolower($appUrl.'/'))) {
                if (strtolower($normalized) !== strtolower($appUrl)) {
                    return null;
                }
            }

            $path = parse_url($candidate, PHP_URL_PATH) ?? '/';
            $query = parse_url($candidate, PHP_URL_QUERY);
            $candidate = $path.(is_string($query) && $query !== '' ? '?'.$query : '');
        }

        if (! str_starts_with($candidate, '/') || str_starts_with($candidate, '//')) {
            return null;
        }

        $parts = parse_url('http://local.invalid'.$candidate);

        if ($parts === false) {
            return null;
        }

        $path = $parts['path'] ?? null;

        if (! is_string($path) || $path === '' || str_contains($path, '..')) {
            return null;
        }

        if (preg_match('#^/programs/([A-Za-z0-9\-_]+)$#', $path, $matches) !== 1) {
            return null;
        }

        $slug = $matches[1];
        $trackValues = array_column(CompetencyTrack::cases(), 'value');

        if (in_array($slug, $trackValues, true)) {
            return null;
        }

        $program = TrainingProgram::query()->where('slug', $slug)->first();

        if ($program === null || $program->status !== ProgramStatus::Published) {
            return null;
        }

        $query = $parts['query'] ?? null;

        return $path.(is_string($query) && $query !== '' ? '?'.$query : '');
    }
}
