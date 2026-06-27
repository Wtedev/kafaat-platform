<?php

namespace App\Services\Exports;

use App\Models\User;
use App\Support\Exports\BeneficiaryProfileExportColumns;

final class BeneficiaryExportAuthorization
{
    /**
     * @param  list<string>  $requestedKeys
     * @return list<string>
     */
    public static function filterAllowedColumnKeys(User $actor, array $requestedKeys): array
    {
        $allowed = array_keys(BeneficiaryProfileExportColumns::optionLabels());
        $keys = array_values(array_intersect($requestedKeys, $allowed));

        return array_values(array_filter(
            $keys,
            fn (string $key): bool => self::canExportColumn($actor, $key),
        ));
    }

    public static function canExportColumn(User $actor, string $key): bool
    {
        if (in_array($key, ['user_email', 'user_phone', 'birth_date'], true)) {
            return $actor->can('exports.beneficiaries.contact');
        }

        if (str_starts_with($key, 'cv_')) {
            return false;
        }

        return $actor->can('exports.beneficiaries.basic');
    }

    /**
     * @return list<string>
     */
    public static function defaultColumnKeysFor(User $actor): array
    {
        return self::filterAllowedColumnKeys($actor, BeneficiaryProfileExportColumns::defaultKeys());
    }
}
