<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filament relationship selects use DISTINCT on preloaded options.
 * PostgreSQL cannot compare json columns for DISTINCT — select id/name only.
 */
final class FilamentUserSelectQuery
{
    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public static function apply(Builder $query, ?callable $extra = null): Builder
    {
        $table = $query->getModel()->getTable();

        $query->select([
            "{$table}.id",
            "{$table}.name",
        ]);

        if ($extra !== null) {
            $extra($query);
        }

        return $query;
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public static function activeOrderedByName(Builder $query): Builder
    {
        return self::apply($query, function (Builder $query): void {
            $table = $query->getModel()->getTable();

            $query->where("{$table}.is_active", true)
                ->orderBy("{$table}.name");
        });
    }
}
