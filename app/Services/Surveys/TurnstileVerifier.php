<?php

namespace App\Services\Surveys;

use Illuminate\Support\Facades\Http;

final class TurnstileVerifier
{
    public const TEST_TOKEN = 'test-turnstile';

    public function passes(?string $token, ?string $ip): bool
    {
        if ($this->fakeEnabled()) {
            return $token === self::TEST_TOKEN;
        }

        $secret = config('services.turnstile.secret_key');
        $siteKey = config('services.turnstile.site_key');
        if (! is_string($secret) || trim($secret) === '' || ! is_string($siteKey) || trim($siteKey) === '') {
            return false;
        }

        if (! is_string($token) || trim($token) === '') {
            return false;
        }

        $response = Http::asForm()
            ->timeout(5)
            ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ]);

        return $response->ok() && $response->json('success') === true;
    }

    public function siteKey(): ?string
    {
        if ($this->fakeEnabled()) {
            return null;
        }

        $siteKey = config('services.turnstile.site_key');

        return is_string($siteKey) && trim($siteKey) !== '' ? $siteKey : null;
    }

    private function fakeEnabled(): bool
    {
        return app()->environment('testing') && config('services.turnstile.fake') === true;
    }
}
