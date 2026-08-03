<?php

namespace App\Support\Auth;

/**
 * Detects case-insensitive duplicate emails (for migration preflight).
 * Does not merge or delete accounts.
 */
final class CaseInsensitiveEmailDuplicateScanner
{
    /**
     * @param  iterable<int, object|array{id: int|string, email: string}>  $rows
     * @return list<array{normalized_email: string, aggregate: int, ids: string}>
     */
    public static function findInRows(iterable $rows): array
    {
        $grouped = collect($rows)
            ->map(function (object|array $row): array {
                $id = is_array($row) ? $row['id'] : $row->id;
                $email = is_array($row) ? $row['email'] : $row->email;

                return [
                    'id' => $id,
                    'normalized' => EmailNormalizer::normalize((string) $email),
                ];
            })
            ->groupBy('normalized')
            ->filter(fn ($group) => $group->count() > 1);

        return $grouped->map(function ($group, string $normalized): array {
            return [
                'normalized_email' => $normalized,
                'aggregate' => $group->count(),
                'ids' => $group->pluck('id')->implode(','),
            ];
        })->values()->all();
    }
}
