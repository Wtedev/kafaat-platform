<?php

namespace Database\Seeders\Support;

use App\Exceptions\UserDeletionNotAllowedException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Demo/test-only hard purge. Not reachable from HTTP-facing application code.
 *
 * @internal
 */
final class DemoEnvironmentUserPurge
{
    public static function purge(User $user): void
    {
        if (app()->environment('production')) {
            throw UserDeletionNotAllowedException::maintenanceDeletionBlocked(
                'Demo user purge is not allowed in production.',
            );
        }

        if (! app()->runningInConsole()) {
            throw UserDeletionNotAllowedException::maintenanceDeletionBlocked(
                'Demo user purge is only allowed from console seeders.',
            );
        }

        if (! (bool) config('app.demo_data_purge_enabled', false)) {
            throw UserDeletionNotAllowedException::maintenanceDeletionBlocked(
                'Demo user purge is disabled. Set APP_DEMO_DATA_PURGE_ENABLED=true for demo databases only.',
            );
        }

        DB::transaction(function () use ($user): void {
            \App\Support\Privacy\UserDeletionGuard::runAuthorized(function () use ($user): void {
                $user->syncRoles([]);
                $user->delete();
            });
        });
    }
}
