<?php

namespace App\Support;

use App\Enums\IdentityType;
use App\Enums\ProfileGender;
use App\Models\TrainingProgram;
use App\Models\User;

/**
 * Structured acceptance / eligibility rules stored on training_programs.acceptance_conditions.
 *
 * Shape:
 * {
 *   "require_saudi_national": bool,
 *   "genders": ["male"|"female", ...],
 *   "gender_capacity_full": ["male"|"female", ...],
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
        'acceptance_gender_capacity_full',
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
            || ((is_array($conditions['gender_capacity_full'] ?? null) ? $conditions['gender_capacity_full'] : []) !== [])
            || self::nullablePositiveInt($conditions['min_age'] ?? null) !== null
            || self::nullablePositiveInt($conditions['max_age'] ?? null) !== null
            || ((is_array($conditions['cities'] ?? null) ? $conditions['cities'] : []) !== []);
    }

    /**
     * @param  array<string, mixed>|null  $conditions
     * @return array{
     *     require_saudi_national: bool,
     *     genders: list<string>,
     *     gender_capacity_full: list<string>,
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

        $genders = self::normalizeGenderList($conditions['genders'] ?? null);
        $genderCapacityFull = self::normalizeGenderList($conditions['gender_capacity_full'] ?? null);

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
            'gender_capacity_full' => $genderCapacityFull,
            'min_age' => $minAge,
            'max_age' => $maxAge,
            'cities' => $cities,
            'require_complete_profile' => (bool) ($conditions['require_complete_profile'] ?? false),
        ];

        return self::hasAny($normalized) ? $normalized : null;
    }

    /**
     * Fields the beneficiary must fill before registering when this program checks them.
     *
     * @return list<'identity_number'|'gender'|'birth_date'>
     */
    public static function missingProfileFieldsForRegistration(TrainingProgram $program, User $user): array
    {
        $conditions = self::normalize(
            is_array($program->acceptance_conditions) ? $program->acceptance_conditions : null,
        );

        if ($conditions === null) {
            return [];
        }

        $user->loadMissing('profile');
        $missing = [];

        if ($conditions['require_saudi_national'] && ! filled($user->identity_number_ciphertext)) {
            $missing[] = 'identity_number';
        }

        if ($conditions['genders'] !== [] && ! $user->profile?->gender instanceof ProfileGender) {
            $missing[] = 'gender';
        }

        if (
            ($conditions['min_age'] !== null || $conditions['max_age'] !== null)
            && $user->profile?->birth_date === null
        ) {
            $missing[] = 'birth_date';
        }

        return $missing;
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
            'gender_capacity_full' => [],
            'min_age' => null,
            'max_age' => null,
            'cities' => [],
            'require_complete_profile' => false,
        ];

        return [
            'acceptance_require_saudi_national' => (bool) $normalized['require_saudi_national'],
            'acceptance_genders' => $normalized['genders'],
            'acceptance_gender_capacity_full' => $normalized['gender_capacity_full'],
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
        $conditionKeys = array_values(array_diff(self::FORM_KEYS, ['acceptance_manual_review']));
        $hasConditionInputs = false;
        foreach ($conditionKeys as $key) {
            if (array_key_exists($key, $data)) {
                $hasConditionInputs = true;
                break;
            }
        }

        if ($hasConditionInputs) {
            $existing = is_array($data['acceptance_conditions'] ?? null) ? $data['acceptance_conditions'] : [];
            $data['acceptance_conditions'] = self::normalize([
                'require_saudi_national' => (bool) ($data['acceptance_require_saudi_national'] ?? false),
                'genders' => is_array($data['acceptance_genders'] ?? null) ? $data['acceptance_genders'] : [],
                'gender_capacity_full' => array_key_exists('acceptance_gender_capacity_full', $data)
                    ? (is_array($data['acceptance_gender_capacity_full']) ? $data['acceptance_gender_capacity_full'] : [])
                    : (is_array($existing['gender_capacity_full'] ?? null) ? $existing['gender_capacity_full'] : []),
                'min_age' => $data['acceptance_min_age'] ?? null,
                'max_age' => $data['acceptance_max_age'] ?? null,
                'cities' => array_key_exists('acceptance_cities', $data)
                    ? (is_array($data['acceptance_cities']) ? $data['acceptance_cities'] : [])
                    : (is_array($existing['cities'] ?? null) ? $existing['cities'] : []),
                'require_complete_profile' => array_key_exists('acceptance_require_complete_profile', $data)
                    ? (bool) $data['acceptance_require_complete_profile']
                    : (bool) ($existing['require_complete_profile'] ?? false),
            ]);
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
            $lines[] = 'سعودي الجنسية (يبدأ رقم الهوية بالرقم 1)';
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

    /**
     * Public-page gender line. An empty list means everyone.
     */
    public static function publicGenderLabel(?array $conditions): string
    {
        $normalized = self::normalize($conditions);
        $genders = is_array($normalized) ? $normalized['genders'] : [];
        $male = in_array(ProfileGender::Male->value, $genders, true);
        $female = in_array(ProfileGender::Female->value, $genders, true);

        if ($female && ! $male) {
            return 'إناث';
        }

        if ($male && ! $female) {
            return 'ذكور';
        }

        return 'ذكور وإناث';
    }

    /**
     * Capacity facts shared by the public page and the staff preview.
     * Unlimited capacity returns no rows; the staff preview adds its own sentence.
     *
     * @return list<array{label: string, value: string}>
     */
    public static function publicCapacityItems(?int $capacity, ?int $capacityMale, ?int $capacityFemale): array
    {
        if ($capacityMale !== null || $capacityFemale !== null) {
            $items = [];
            if ($capacityMale !== null) {
                $items[] = ['label' => 'سعة الرجال', 'value' => (string) $capacityMale];
            }
            if ($capacityFemale !== null) {
                $items[] = ['label' => 'سعة النساء', 'value' => (string) $capacityFemale];
            }

            return $items;
        }

        if ($capacity !== null) {
            return [['label' => 'السعة', 'value' => (string) $capacity]];
        }

        return [];
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

    public static function genderCapacityFullMessage(string $gender): string
    {
        return match ($gender) {
            ProfileGender::Female->value => 'نأسف بإبلاغكم انتهت مقاعد التسجيل للإناث',
            ProfileGender::Male->value => 'نأسف بإبلاغكم انتهت مقاعد التسجيل للذكور',
            default => 'نأسف بإبلاغكم انتهت مقاعد التسجيل',
        };
    }

    /**
     * @param  list<string>  $reasons
     */
    public static function isGenderCapacityFullReasonsOnly(array $reasons): bool
    {
        if ($reasons === []) {
            return false;
        }

        $capacityMessages = [
            self::genderCapacityFullMessage(ProfileGender::Female->value),
            self::genderCapacityFullMessage(ProfileGender::Male->value),
            self::genderCapacityFullMessage(''),
        ];

        foreach ($reasons as $reason) {
            if (! in_array($reason, $capacityMessages, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function normalizeGenderList(mixed $values): array
    {
        return collect(is_array($values) ? $values : [])
            ->map(static fn (mixed $v): string => (string) $v)
            ->filter(static fn (string $v): bool => in_array($v, [
                ProfileGender::Male->value,
                ProfileGender::Female->value,
            ], true))
            ->unique()
            ->values()
            ->all();
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
