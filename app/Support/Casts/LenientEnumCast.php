<?php

namespace App\Support\Casts;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Enum cast that maps unknown DB values to null instead of throwing (production-safe).
 *
 * @template T of BackedEnum
 */
final class LenientEnumCast implements CastsAttributes
{
    public function __construct(
        private readonly string $enumClass,
    ) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ($this->enumClass)::tryFrom((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return ($this->enumClass)::tryFrom((string) $value)?->value;
    }
}
