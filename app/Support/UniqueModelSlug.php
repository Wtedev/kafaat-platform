<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class UniqueModelSlug
{
    /**
     * @param  class-string<Model>|Model  $model
     */
    public static function fromTitle(
        Model|string $model,
        string $title,
        string $column = 'slug',
        string $fallbackPrefix = 'item',
        int|string|null $ignoreId = null,
    ): string {
        $base = Str::slug($title);

        if ($base === '') {
            $base = $fallbackPrefix.'-'.Str::lower(Str::random(8));
        }

        $slug = $base;
        $suffix = 1;

        while (self::exists($model, $column, $slug, $ignoreId)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * @param  class-string<Model>|Model  $model
     */
    private static function exists(
        Model|string $model,
        string $column,
        string $slug,
        int|string|null $ignoreId,
    ): bool {
        $query = is_string($model)
            ? $model::query()
            : $model->newQuery();

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->where($column, $slug)->exists();
    }
}
