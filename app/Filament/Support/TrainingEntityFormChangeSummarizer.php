<?php

namespace App\Filament\Support;

use App\Enums\LearningPathKind;
use App\Enums\PathStatus;
use App\Enums\ProgramStatus;
use App\Enums\TrainingProgramKind;
use App\Models\LearningPath;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class TrainingEntityFormChangeSummarizer
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, string>  $labels
     * @return array<int, array{key: string, label: string, old: string, new: string}>
     */
    public static function structuredChanges(array $before, array $after, array $labels = []): array
    {
        $changes = [];

        foreach (static::relevantKeys($before, $after) as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            if (static::valuesEqual($old, $new)) {
                continue;
            }

            $changes[] = [
                'key' => $key,
                'label' => $labels[$key] ?? static::defaultLabel($key),
                'old' => static::formatValue($key, $old),
                'new' => static::formatValue($key, $new),
            ];
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, string>  $labels
     */
    public static function toHtml(array $before, array $after, array $labels = []): HtmlString
    {
        $changes = static::structuredChanges($before, $after, $labels);

        if ($changes === []) {
            return new HtmlString(
                view('filament.components.settings-changes-summary', ['changes' => []])->render()
            );
        }

        return new HtmlString(
            view('filament.components.settings-changes-summary', ['changes' => $changes])->render()
        );
    }

    public static function hasChanges(array $before, array $after): bool
    {
        return static::describeChanges($before, $after) !== [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function comparableSnapshot(array $data): array
    {
        $snapshot = [];

        $keys = array_values(array_filter(
            array_keys($data),
            fn (string $key): bool => ! in_array($key, static::ignoredKeys(), true),
        ));
        sort($keys);

        foreach ($keys as $key) {
            $snapshot[$key] = static::normalizeValueForComparison($data[$key] ?? null);
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    private static function normalizeValueForComparison(mixed $value): mixed
    {
        if (static::isBooleanLike($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        if (is_array($value)) {
            return static::normalizeComparable($value);
        }

        if (is_string($value) && static::normalizeDateComparable($value) !== null) {
            return static::normalizeDateComparable($value);
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }

    private static function isBooleanLike(mixed $value): bool
    {
        if (is_bool($value)) {
            return true;
        }

        return in_array($value, [0, 1, '0', '1', 'true', 'false'], true);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    public static function describeChanges(array $before, array $after, array $labels = []): array
    {
        return array_map(
            static fn (array $change): string => $change['label'].': من «'.$change['old'].'» إلى «'.$change['new'].'»',
            static::structuredChanges($before, $after, $labels),
        );
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<int, string>
     */
    private static function relevantKeys(array $before, array $after): array
    {
        $keys = array_unique([...array_keys($before), ...array_keys($after)]);
        sort($keys);

        return array_values(array_filter(
            $keys,
            fn (string $key): bool => ! in_array($key, static::ignoredKeys(), true),
        ));
    }

    /**
     * @return array<int, string>
     */
    private static function ignoredKeys(): array
    {
        return [
            'id',
            'slug',
            'created_at',
            'updated_at',
            'created_by',
            'updated_by',
            'registration_status_display',
        ];
    }

    private static function defaultLabel(string $key): string
    {
        return match ($key) {
            'title' => 'العنوان',
            'description' => 'الوصف',
            'image' => 'صورة الغلاف',
            'program_kind' => 'نوع البرنامج',
            'path_kind' => 'نوع المسار',
            'capacity' => 'الحد الأقصى للمسجّلين',
            'capacity_unlimited' => 'تسجيل غير محدود',
            'auto_accept_registrations' => 'قبول تلقائي',
            'acceptance_manual_review' => 'قبول يدوي',
            'acceptance_require_saudi_national' => 'سعودي الجنسية',
            'acceptance_require_complete_profile' => 'اكتمال بيانات الملف الشخصي',
            'acceptance_genders' => 'الجنس',
            'acceptance_min_age' => 'الحد الأدنى للعمر',
            'acceptance_max_age' => 'الحد الأقصى للعمر',
            'acceptance_cities' => 'مدن الإقامة',
            'acceptance_conditions' => 'شروط القبول',
            'start_date' => 'تاريخ البدء',
            'end_date' => 'تاريخ الانتهاء',
            'registration_start' => 'بداية التسجيل',
            'registration_end' => 'نهاية التسجيل',
            'weekdays' => 'أيام البرنامج',
            'status' => 'حالة النشر',
            'published_at' => 'موعد النشر',
            'publish_immediately' => 'نشر فوراً',
            'notify_audience' => 'إشعارات المستفيدين',
            'notify_on_publish' => 'إشعارات المستفيدين',
            'notify_milestones' => 'إشعارات المحطات',
            'notify_registrants_on_update' => 'تنبيه المسجّلين',
            'is_linked_to_path' => 'تابع لمسار تدريبي',
            'learning_path_id' => 'المسار التدريبي',
            'owner_id' => 'مالك البرنامج',
            'assigned_to' => 'المسؤول',
            'editors' => 'أعضاء فريق العمل',
            default => Str::headline(str_replace('_', ' ', $key)),
        };
    }

    private static function formatValue(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($key) {
            'program_kind' => static::programKindLabel($value),
            'path_kind' => static::pathKindLabel($value),
            'status' => static::statusLabel($value),
            'publish_immediately', 'capacity_unlimited', 'auto_accept_registrations', 'acceptance_manual_review', 'acceptance_require_saudi_national', 'acceptance_require_complete_profile', 'notify_audience', 'is_linked_to_path', 'notify_on_publish', 'notify_milestones', 'notify_registrants_on_update' => static::boolLabel($value),
            'learning_path_id' => LearningPath::query()->whereKey($value)->value('title') ?? (string) $value,
            'owner_id', 'assigned_to' => User::query()->whereKey($value)->value('name') ?? (string) $value,
            'editors' => static::formatUserIds($value),
            'weekdays' => static::formatWeekdays($value),
            'image' => is_array($value) ? 'صورة جديدة' : (filled($value) ? 'صورة مرفوعة' : '—'),
            'published_at', 'start_date', 'end_date', 'registration_start', 'registration_end' => static::formatDate($value),
            default => is_array($value) ? implode('، ', array_map(strval(...), $value)) : (string) $value,
        };
    }

    private static function programKindLabel(mixed $value): string
    {
        if ($value instanceof TrainingProgramKind) {
            return $value->label();
        }

        return TrainingProgramKind::tryFrom((string) $value)?->label() ?? (string) $value;
    }

    private static function pathKindLabel(mixed $value): string
    {
        if ($value instanceof LearningPathKind) {
            return $value->label();
        }

        return LearningPathKind::tryFrom((string) $value)?->label() ?? (string) $value;
    }

    private static function statusLabel(mixed $value): string
    {
        if ($value instanceof ProgramStatus || $value instanceof PathStatus) {
            return $value->label();
        }

        return ProgramStatus::tryFrom((string) $value)?->label()
            ?? PathStatus::tryFrom((string) $value)?->label()
            ?? (string) $value;
    }

    private static function boolLabel(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'مفعّل' : 'معطّل';
    }

    private static function formatUserIds(mixed $value): string
    {
        if (! is_array($value)) {
            return (string) $value;
        }

        $names = User::query()->whereIn('id', $value)->orderBy('name')->pluck('name');

        return $names->isEmpty() ? '—' : $names->implode('، ');
    }

    private static function formatWeekdays(mixed $value): string
    {
        if (! is_array($value)) {
            return (string) $value;
        }

        $labels = [
            0 => 'الأحد',
            1 => 'الإثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
            6 => 'السبت',
        ];

        return collect($value)
            ->map(fn ($day): string => $labels[(int) $day] ?? (string) $day)
            ->implode('، ');
    }

    private static function formatDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        try {
            if ($value instanceof Carbon) {
                return $value->timezone(config('app.timezone'))->format('Y/m/d');
            }

            return Carbon::parse($value)->timezone(config('app.timezone'))->format('Y/m/d');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private static function valuesEqual(mixed $old, mixed $new): bool
    {
        if (static::isBooleanLike($old) && static::isBooleanLike($new)) {
            return filter_var($old, FILTER_VALIDATE_BOOLEAN) === filter_var($new, FILTER_VALIDATE_BOOLEAN);
        }

        $oldNormalized = static::normalizeValueForComparison($old);
        $newNormalized = static::normalizeValueForComparison($new);

        return $oldNormalized === $newNormalized;
    }

    private static function normalizeDateComparable(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

  /**
   * @return array<int, mixed>|string|null
   */
    private static function normalizeComparable(mixed $value): array|string|null
    {
        if (! is_array($value)) {
            return $value;
        }

        $normalized = array_map(
            static fn (mixed $item): string => is_scalar($item) || $item === null ? (string) $item : json_encode($item),
            $value,
        );
        sort($normalized);

        return $normalized;
    }
}
