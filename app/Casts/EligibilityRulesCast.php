<?php

namespace App\Casts;

use App\Data\Certificates\EligibilityRules;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<EligibilityRules, mixed>
 */
final class EligibilityRulesCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): EligibilityRules
    {
        return EligibilityRules::fromArray($this->decode($value));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $rules = $value instanceof EligibilityRules
            ? $value
            : EligibilityRules::fromArray(is_array($value) ? $value : []);

        return json_encode($rules->toArray(), JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    public function compare(Model $model, string $key, mixed $original, mixed $value): bool
    {
        return json_encode($this->decode($original)) === json_encode($this->decode($value));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
