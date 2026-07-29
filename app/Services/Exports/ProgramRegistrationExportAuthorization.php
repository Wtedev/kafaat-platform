<?php

namespace App\Services\Exports;

use App\Models\TrainingProgram;
use App\Models\User;
use App\Support\Exports\ProgramRegistrationExportColumns;

final class ProgramRegistrationExportAuthorization
{
    public static function canExport(User $actor, TrainingProgram $program): bool
    {
        if (! $actor->can('exports.training')) {
            return false;
        }

        return $actor->can('viewOperational', $program);
    }

    /**
     * @param  list<string>  $requestedKeys
     * @return list<string>
     */
    public static function filterAllowedColumnKeys(User $actor, array $requestedKeys): array
    {
        $allowed = ProgramRegistrationExportColumns::allowlistedKeys($actor);

        return array_values(array_intersect($requestedKeys, $allowed));
    }

    /**
     * @return list<string>
     */
    public static function defaultColumnKeysFor(User $actor): array
    {
        return self::filterAllowedColumnKeys(
            $actor,
            ProgramRegistrationExportColumns::defaultKeys($actor),
        );
    }
}
