<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\File;

final class ProductionEnvironmentValidator
{
    /**
     * @return list<string>
     */
    public function violations(): array
    {
        if (config('app.env') !== 'production') {
            return [];
        }

        $issues = [];

        if (config('app.debug')) {
            $issues[] = 'APP_DEBUG must be false in production.';
        }

        if (blank(config('app.key'))) {
            $issues[] = 'APP_KEY is missing.';
        }

        foreach ((array) config('security.production.required_env', []) as $key) {
            // IDENTITY_LOOKUP_KEY is read via config/identity.php; after config:cache,
            // env() is null outside config files — check the resolved config value.
            $missing = $key === 'IDENTITY_LOOKUP_KEY'
                ? blank(config('identity.lookup_key'))
                : blank(env($key));

            if ($missing) {
                $issues[] = "Required environment variable {$key} is missing.";
            }
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $issues[] = 'APP_URL must use HTTPS in production.';
        }

        if (! config('session.secure')) {
            $issues[] = 'SESSION_SECURE_COOKIE must be true in production.';
        }

        if (! config('session.http_only', true)) {
            $issues[] = 'SESSION_HTTP_ONLY must be true in production.';
        }

        $queue = (string) config('queue.default');
        if (in_array($queue, (array) config('security.production.forbidden_queue_connections', []), true)) {
            $issues[] = "QUEUE_CONNECTION cannot be {$queue} in production.";
        }

        $sessionDriver = (string) config('session.driver');
        if (in_array($sessionDriver, (array) config('security.production.forbidden_session_drivers', []), true)) {
            $issues[] = "SESSION_DRIVER cannot be {$sessionDriver} in production.";
        }

        $privateDisk = (string) config('privacy.export.disk', 'private_documents');
        $privateRoot = config("filesystems.disks.{$privateDisk}.root");
        $publicRoot = storage_path('app/public');
        if (is_string($privateRoot) && realpath($privateRoot) === realpath($publicRoot)) {
            $issues[] = 'Private documents disk must not point to public storage.';
        }

        if ((string) config('mail.default') === 'log') {
            $issues[] = 'MAIL_MAILER should not be log in production.';
        }

        if (class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $issues[] = 'Laravel Telescope must not be enabled in production.';
        }

        if (File::exists(base_path('vendor/barryvdh/laravel-debugbar'))) {
            $issues[] = 'Debugbar package must not be deployed to production.';
        }

        return $issues;
    }
}
