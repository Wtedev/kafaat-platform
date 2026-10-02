<?php

namespace App\Casts;

use App\Data\Certificates\CertificateElement;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<list<CertificateElement>, mixed>
 */
final class CertificateElementsCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        $decoded = $this->decode($value);

        return array_map(
            fn (array $row): CertificateElement => CertificateElement::fromArray($row),
            $decoded,
        );
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $items = [];

        foreach ($value ?? [] as $item) {
            $element = $item instanceof CertificateElement
                ? $item
                : CertificateElement::fromArray(is_array($item) ? $item : []);
            $items[] = $element->toArray();
        }

        return json_encode($items, JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    public function compare(Model $model, string $key, mixed $original, mixed $value): bool
    {
        return $this->canonical($original) === $this->canonical($value);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            $decoded = $value;
        } elseif (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
        } else {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, 'is_array'));
    }

    private function canonical(mixed $value): string
    {
        return json_encode($this->decode($value), JSON_UNESCAPED_UNICODE) ?: '[]';
    }
}
