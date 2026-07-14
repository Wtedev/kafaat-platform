<?php

namespace App\Support;

use App\Enums\IdentityType;
use App\Enums\ProfileGender;

/**
 * Structured acceptance / eligibility rules stored on training_programs.acceptance_conditions.
 *
 * Shape:
 * {
 *   "require_saudi_national": bool,
 *   "genders": ["male"|"female", ...],
 *   "min_age": int|null,
 *   "max_age": int|null,
 *   "cities": ["الرياض", ...],
 *   "require_complete_profile": bool
 * }
 */
final class ProgramAcceptanceConditions
{
    public const FORM_KEYS = [
        'acceptance_require_saudi_national',
        'acceptance_genders',
        'acceptance_min_age',
        'acceptance_max_age',
        'acceptance_cities',
        'acceptance_require_complete_profile',
        'acceptance_manual_review',
    ];

    /**
     * @param  array<string, mixed>|null  $conditions
     */
    public static function hasAny(?array $conditions): bool
    {
        if (! is_array($conditions) || $conditions === []) {
            return false;
        }

        return (bool) ($conditions['require_saudi_national'] ?? false)
            || (bool) ($conditions['require_complete_profile'] ?? false)
            || ((is_array($conditions['genders'] ?? null) ? $conditions['genders'] : []) !== [])
            || self::nullablePositiveInt($conditions['min_age'] ?? null) !== null
            || self::nullablePositiveInt($conditions['max_age'] ?? null) !== null
            || ((is_array($conditions['cities'] ?? null) ? $conditions['cities'] : []) !== []);
    }

    /**
     * @param  array<string, mixed>|null  $conditions
     * @return array{
     *     require_saudi_national: bool,
     *     genders: list<string>,
     *     min_age: int|null,
     *     max_age: int|null,
     *     cities: list<string>,
     *     require_complete_profile: bool
     * }|null
     */
    public static function normalize(?array $conditions): ?array
    {
        if (! is_array($conditions) || $conditions === []) {
            return null;
        }

        $genders = collect(is_array($conditions['genders'] ?? null) ? $conditions['genders'] : [])
            ->map(static fn (mixed $v): string => (string) $v)
            ->filter(static fn (string $v): bool => in_array($v, [
                ProfileGender::Male->value,
                ProfileGender::Female->value,
            ], true))
            ->unique()
            ->values()
            ->all();

        $cities = collect(is_array($conditions['cities'] ?? null) ? $conditions['cities'] : [])
            ->map(static fn (mixed $v): string => self::normalizeCity((string) $v))
            ->filter(static fn (string $v): bool => $v !== '')
            ->unique()
            ->values()
            ->all();

        $minAge = self::nullablePositiveInt($conditions['min_age'] ?? null);
        $maxAge = self::nullablePositiveInt($conditions['max_age'] ?? null);

        if ($minAge !== null && $maxAge !== null && $minAge > $maxAge) {
            [$minAge, $maxAge] = [$maxAge, $minAge];
        }

        $normalized = [
            'require_saudi_national' => (bool) ($conditions['require_saudi_national'] ?? false),
            'genders' => $genders,
            'min_age' => $minAge,
            'max_age' => $maxAge,
            'cities' => $cities,
            'require_complete_profile' => (bool) ($conditions['require_complete_profile'] ?? false),
        ];

        return self::hasAny($normalized) ? $normalized : null;
    }

    /**
     * Unpack stored JSON into Filament form flat fields.
     *
     * @param  array<string, mixed>|null  $conditions
     * @return array<string, mixed>
     */
    public static function toFormState(?array $conditions, bool $autoAccept): array
    {
        $normalized = self::normalize($conditions) ?? [
            'require_saudi_national' => false,
            'genders' => [],
            'min_age' => null,
            'max_age' => null,
            'cities' => [],
            'require_complete_profile' => false,
        ];

        return [
            'acceptance_require_saudi_national' => (bool) $normalized['require_saudi_national'],
            'acceptance_genders' => $normalized['genders'],
            'acceptance_min_age' => $normalized['min_age'],
            'acceptance_max_age' => $normalized['max_age'],
            'acceptance_cities' => $normalized['cities'],
            'acceptance_require_complete_profile' => (bool) $normalized['require_complete_profile'],
            // When auto is off and conditions already exist, keep the conditions panel visible.
            'acceptance_manual_review' => ! $autoAccept && self::hasAny($normalized),
        ];
    }

    /**
     * Pack Filament form fields into acceptance_conditions JSON (or null).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function applyFormData(array $data): array
    {
        $autoAccept = (bool) ($data['auto_accept_registrations'] ?? true);
        $manualReview = (bool) ($data['acceptance_manual_review'] ?? false);

        $shouldPersistConditions = $autoAccept || $manualReview;

        if ($shouldPersistConditions) {
            $data['acceptance_conditions'] = self::normalize([
                'require_saudi_national' => (bool) ($data['acceptance_require_saudi_national'] ?? false),
                'genders' => is_array($data['acceptance_genders'] ?? null) ? $data['acceptance_genders'] : [],
                'min_age' => $data['acceptance_min_age'] ?? null,
                'max_age' => $data['acceptance_max_age'] ?? null,
                'cities' => is_array($data['acceptance_cities'] ?? null) ? $data['acceptance_cities'] : [],
                'require_complete_profile' => (bool) ($data['acceptance_require_complete_profile'] ?? false),
            ]);
        } else {
            $data['acceptance_conditions'] = null;
        }

        foreach (self::FORM_KEYS as $key) {
            unset($data[$key]);
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    public static function summarize(?array $conditions): array
    {
        $normalized = self::normalize($conditions);

        if ($normalized === null) {
            return [];
        }

        $lines = [];

        if ($normalized['require_saudi_national']) {
            $lines[] = 'سعودي الجنسية (هوية وطنية)';
        }

        if ($normalized['genders'] !== []) {
            $labels = collect($normalized['genders'])
                ->map(static function (string $value): string {
                    return ProfileGender::tryFrom($value)?->label() ?? $value;
                })
                ->implode('، ');
            $lines[] = 'الجنس: '.$labels;
        }

        if ($normalized['min_age'] !== null || $normalized['max_age'] !== null) {
            $min = $normalized['min_age'];
            $max = $normalized['max_age'];
            if ($min !== null && $max !== null) {
                $lines[] = 'العمر من '.$min.' إلى '.$max.' سنة';
            } elseif ($min !== null) {
                $lines[] = 'العمر من '.$min.' سنة فأكثر';
            } else {
                $lines[] = 'العمر حتى '.$max.' سنة';
            }
        }

        if ($normalized['cities'] !== []) {
            $lines[] = 'مدينة الإقامة: '.implode('، ', $normalized['cities']);
        }

        if ($normalized['require_complete_profile']) {
            $lines[] = 'اكتمال بيانات الملف الشخصي';
        }

        return $lines;
    }

    public static function normalizeCity(string $city): string
    {
        $city = trim(preg_replace('/\s+/u', ' ', $city) ?? '');

        return $city;
    }

    public static function citiesMatch(string $userCity, array $allowedCities): bool
    {
        $needle = mb_strtolower(self::normalizeCity($userCity));

        if ($needle === '') {
            return false;
        }

        foreach ($allowedCities as $allowed) {
            $hay = mb_strtolower(self::normalizeCity((string) $allowed));
            if ($hay !== '' && $needle === $hay) {
                return true;
            }
        }

        return false;
    }

    public static function identityTypeLabel(IdentityType $type): string
    {
        return $type->label();
    }

    private static function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int >= 0 ? $int : null;
    }
}
